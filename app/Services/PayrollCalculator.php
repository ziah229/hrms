<?php

namespace App\Services;

use App\Models\AdhocComponent;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\Holiday;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\OvertimeRequest;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRegister;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung register gaji per karyawan untuk satu periode (rancangan 4.6):
 * gaji pokok + allowance (tetap, sekali bayar, lembur) - deduction (tetap, sekali bayar,
 * absensi, cicilan) - BPJS - PPh 21 TER = take home pay.
 */
class PayrollCalculator
{
    /** @var Collection<string, PayComponent> */
    protected Collection $system;

    protected int $workingDays;

    public function __construct(protected PayrollPeriod $period)
    {
        $this->system = PayComponent::whereIn('code', [
            'ALW-OVT', 'DED-BPJS-KES', 'DED-BPJS-TK', 'DED-PPH21', 'DED-LAT', 'DED-ALP', 'DED-LOAN',
        ])->get()->keyBy('code');

        $holidays = Holiday::whereBetween('holiday_date', [$period->start_date, $period->end_date])
            ->pluck('holiday_date')->map->toDateString()->all();

        $this->workingDays = collect(CarbonPeriod::create($period->start_date, $period->end_date))
            ->filter(fn ($d) => ! $d->isWeekend() && ! in_array($d->toDateString(), $holidays))
            ->count();
    }

    /**
     * Proses seluruh karyawan aktif. Mengembalikan jumlah register yang dibuat.
     */
    public function run(): int
    {
        $employees = Employee::with(['department', 'position', 'primaryBank'])
            ->where('is_active', true)
            ->whereDate('join_date', '<=', $this->period->end_date)
            ->get();

        $count = 0;

        DB::transaction(function () use ($employees, &$count) {
            // Proses ulang: hapus hasil sebelumnya (hanya selama periode belum final).
            $this->period->registers()->delete();
            LoanInstallment::where('payroll_period_id', $this->period->id)->delete();

            foreach ($employees as $employee) {
                if ($this->calculate($employee)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function calculate(Employee $employee): ?PayrollRegister
    {
        $start = $this->period->start_date->toDateString();
        $end = $this->period->end_date->toDateString();

        $salary = $employee->salaryAt($end);
        if (! $salary) {
            return null;
        }

        $basic = (float) $salary->basic_salary;
        $items = [];

        // Ringkasan absensi periode.
        $att = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start, $end])
            ->selectRaw("SUM(status IN ('hadir','terlambat','dinas')) as present")
            ->selectRaw("SUM(status = 'alpha') as absent")
            ->selectRaw("SUM(status = 'terlambat') as late_count")
            ->selectRaw('SUM(late_minutes) as late_minutes')
            ->first();
        $present = (int) $att->present;
        $absent = (int) $att->absent;
        $lateMinutes = (int) $att->late_minutes;

        $overtimeHours = (float) OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('overtime_date', [$start, $end])
            ->sum('hours');

        // Komponen tetap/berulang per karyawan yang berlaku di periode.
        $assigned = EmployeeComponent::with('payComponent')
            ->where('employee_id', $employee->id)
            ->where('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->get()
            ->filter(fn ($c) => $c->payComponent?->is_active);

        foreach ($assigned as $ec) {
            $component = $ec->payComponent;
            $amount = match ($component->calc_method) {
                'percent' => $basic * (float) $ec->value / 100,
                'per_day' => (float) $ec->value * $present,
                default => (float) $ec->value,
            };
            $items[] = $this->item($component, $component->name, $amount);
        }

        // Komponen sekali bayar.
        $adhoc = AdhocComponent::with('payComponent')
            ->where('employee_id', $employee->id)
            ->where('payroll_period_id', $this->period->id)
            ->get();
        foreach ($adhoc as $a) {
            $items[] = $this->item($a->payComponent, $a->payComponent->name.($a->notes ? " ({$a->notes})" : ''), (float) $a->amount);
        }

        // Lembur dari absensi.
        if ($overtimeHours > 0 && ($c = $this->system->get('ALW-OVT'))) {
            $hourly = $basic / config('payroll.overtime_divider');
            $items[] = $this->item($c, "Lembur {$overtimeHours} jam", $hourly * $overtimeHours * config('payroll.overtime_multiplier'));
        }

        // Potongan absensi.
        if ($lateMinutes > 0 && ($c = $this->system->get('DED-LAT'))) {
            $items[] = $this->item($c, "Potongan Keterlambatan ({$lateMinutes} menit)", $lateMinutes * (float) $c->default_value);
        }
        if ($absent > 0 && $this->workingDays > 0 && ($c = $this->system->get('DED-ALP'))) {
            $items[] = $this->item($c, "Potongan Alpha ({$absent} hari)", $basic / $this->workingDays * $absent);
        }

        // Cicilan pinjaman/kasbon.
        $loans = Loan::where('employee_id', $employee->id)->where('status', 'active')
            ->where('remaining_amount', '>', 0)->whereDate('start_date', '<=', $end)->get();
        foreach ($loans as $loan) {
            $installment = min((float) $loan->installment_amount, (float) $loan->remaining_amount);
            if ($c = $this->system->get('DED-LOAN')) {
                $items[] = $this->item($c, 'Cicilan Kasbon/Pinjaman', $installment);
            }
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'payroll_period_id' => $this->period->id,
                'amount' => $installment,
                'status' => 'scheduled',
            ]);
        }

        // BPJS (porsi karyawan).
        $fixedAllowance = collect($items)->where('type', 'allowance')->where('fixed', true)->sum('amount');
        $bpjsBase = $basic + $fixedAllowance;
        $bpjs = config('payroll.bpjs');
        if ($c = $this->system->get('DED-BPJS-KES')) {
            $items[] = $this->item($c, 'BPJS Kesehatan (1%)', min($bpjsBase, $bpjs['kesehatan_cap']) * $bpjs['kesehatan_rate']);
        }
        if ($c = $this->system->get('DED-BPJS-TK')) {
            $tk = $bpjsBase * $bpjs['jht_rate'] + min($bpjsBase, $bpjs['jp_cap']) * $bpjs['jp_rate'];
            $items[] = $this->item($c, 'BPJS Ketenagakerjaan (JHT 2% + JP 1%)', $tk);
        }

        $items = array_values(array_filter($items, fn ($i) => $i['amount'] > 0));
        $totalAllowance = collect($items)->where('type', 'allowance')->sum('amount');
        $totalDeduction = collect($items)->where('type', 'deduction')->sum('amount');

        // PPh 21 metode TER atas penghasilan bruto kena pajak.
        $taxableGross = $basic + collect($items)->where('type', 'allowance')->where('taxable', true)->sum('amount');
        $tax = $this->pph21Ter($taxableGross, $employee->ptkp_status ?: 'TK/0');

        $register = PayrollRegister::create([
            'payroll_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'basic_salary' => $basic,
            'total_allowance' => round($totalAllowance),
            'total_deduction' => round($totalDeduction),
            'tax_amount' => round($tax),
            'net_salary' => round($basic + $totalAllowance - $totalDeduction - $tax),
            'present_days' => $present,
            'absent_days' => $absent,
            'late_count' => (int) $att->late_count,
            'overtime_hours' => $overtimeHours,
            'status' => $adhoc->isNotEmpty() ? 'review' : 'verified',
            'snapshot' => [
                'employee_no' => $employee->employee_no,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'employment_status' => $employee->employment_status,
                'ptkp_status' => $employee->ptkp_status,
                'npwp' => $employee->npwp,
                'bank_name' => $employee->primaryBank?->bank_name,
                'account_no' => $employee->primaryBank?->account_no,
                'account_name' => $employee->primaryBank?->account_name,
                'working_days' => $this->workingDays,
                'late_minutes' => $lateMinutes,
                'salary_type' => $salary->salary_type,
            ],
        ]);

        foreach ($items as $i) {
            $register->items()->create([
                'pay_component_id' => $i['pay_component_id'],
                'type' => $i['type'],
                'description' => $i['description'],
                'amount' => round($i['amount']),
            ]);
        }
        if ($tax > 0) {
            $register->items()->create([
                'pay_component_id' => $this->system->get('DED-PPH21')?->id,
                'type' => 'deduction',
                'description' => 'PPh 21 (TER Kategori '.$this->terCategory($employee->ptkp_status).')',
                'amount' => round($tax),
            ]);
        }

        return $register;
    }

    public function terCategory(?string $ptkp): string
    {
        return config('payroll.ter_category')[$ptkp ?: 'TK/0'] ?? 'A';
    }

    public function pph21Ter(float $gross, ?string $ptkp): float
    {
        foreach (config('payroll.ter_rates')[$this->terCategory($ptkp)] as [$limit, $rate]) {
            if ($limit === null || $gross <= $limit) {
                return $gross * $rate / 100;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(?PayComponent $component, string $description, float $amount): array
    {
        return [
            'pay_component_id' => $component?->id,
            'type' => $component?->type ?? 'allowance',
            'fixed' => $component?->nature === 'fixed',
            'taxable' => (bool) $component?->is_taxable,
            'description' => $description,
            'amount' => $amount,
        ];
    }
}
