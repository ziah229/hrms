<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\PayrollPeriod;
use App\Models\PayrollRegister;
use App\Models\PayrollRegisterItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generator data report. Setiap report mengembalikan ['columns' => [...], 'rows' => [[...]], 'subtitle' => '...'].
 */
class ReportService
{
    protected Collection $employeeIds;

    public function __construct(protected User $user, protected array $filters = [])
    {
        $this->employeeIds = Employee::visibleTo($user)
            ->when($filters['department_id'] ?? null, fn ($q, $d) => $q->where('department_id', $d))
            ->pluck('id');
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, mixed>>, subtitle: string, money: array<int, int>}
     */
    public function run(string $code): array
    {
        $method = 'report'.str_replace(' ', '', ucwords(strtolower(str_replace('-', ' ', $code))));
        abort_unless(method_exists($this, $method), 404, 'Report belum tersedia.');

        return $this->{$method}() + ['money' => []];
    }

    protected function month(): Carbon
    {
        return Carbon::parse(($this->filters['month'] ?? now()->format('Y-m')).'-01');
    }

    protected function period(): ?PayrollPeriod
    {
        return isset($this->filters['period'])
            ? PayrollPeriod::find($this->filters['period'])
            : PayrollPeriod::where('status', 'final')->latest('end_date')->first();
    }

    protected function registers(): Collection
    {
        $period = $this->period();

        return $period
            ? PayrollRegister::with(['items.payComponent'])->where('payroll_period_id', $period->id)->whereIn('employee_id', $this->employeeIds)->orderBy('employee_id')->get()
            : collect();
    }

    // ------------------------------------------------------------ Karyawan

    protected function reportEmpList(): array
    {
        $rows = Employee::with(['department', 'position'])->whereIn('id', $this->employeeIds)
            ->when($this->filters['status'] ?? null, fn ($q, $s) => $q->where('employment_status', $s))
            ->where('is_active', true)->orderBy('employee_no')->get()
            ->map(fn ($e) => [$e->employee_no, $e->full_name, $e->department?->name, $e->position?->name,
                Employee::EMPLOYMENT_STATUSES[$e->employment_status], $e->join_date?->format('d/m/Y'), $e->email, $e->phone]);

        return ['columns' => ['NIK', 'Nama', 'Departemen', 'Jabatan', 'Status', 'Tgl Masuk', 'Email', 'Telepon'], 'rows' => $rows->all(), 'subtitle' => 'Karyawan aktif'];
    }

    protected function reportEmpHeadcount(): array
    {
        $rows = Employee::with('department')->whereIn('id', $this->employeeIds)->where('is_active', true)->get()
            ->groupBy(fn ($e) => $e->department?->name ?? '-')
            ->map(fn ($g, $dept) => [$dept, $g->where('employment_status', 'tetap')->count(), $g->where('employment_status', 'kontrak')->count(),
                $g->where('employment_status', 'magang')->count(), $g->where('gender', 'L')->count(), $g->where('gender', 'P')->count(), $g->count()])
            ->sortKeys()->values();

        return ['columns' => ['Departemen', 'Tetap', 'Kontrak', 'Magang', 'Laki-laki', 'Perempuan', 'Total'], 'rows' => $rows->all(), 'subtitle' => 'Per '.now()->translatedFormat('d F Y')];
    }

    protected function reportEmpContract(): array
    {
        $rows = EmployeeContract::with('employee.department')->whereIn('employee_id', $this->employeeIds)
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])->orderBy('end_date')->get()
            ->map(fn ($c) => [$c->employee->employee_no, $c->employee->full_name, $c->employee->department?->name, $c->contract_type,
                $c->start_date->format('d/m/Y'), $c->end_date->format('d/m/Y'), (int) now()->diffInDays($c->end_date).' hari']);

        return ['columns' => ['NIK', 'Nama', 'Departemen', 'Jenis', 'Mulai', 'Berakhir', 'Sisa'], 'rows' => $rows->all(), 'subtitle' => 'Berakhir dalam 60 hari'];
    }

    // ------------------------------------------------------------ Absensi

    protected function reportAttMonthly(): array
    {
        $m = $this->month();
        $stats = Attendance::whereIn('employee_id', $this->employeeIds)
            ->whereBetween('attendance_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])
            ->selectRaw("employee_id, SUM(status='hadir') h, SUM(status='terlambat') t, SUM(status IN ('izin','sakit')) i, SUM(status='cuti') c, SUM(status='alpha') a, SUM(late_minutes) lm, SUM(work_hours) wh")
            ->groupBy('employee_id')->get()->keyBy('employee_id');

        $rows = Employee::with('department')->whereIn('id', $stats->keys())->orderBy('employee_no')->get()
            ->map(fn ($e) => [$e->employee_no, $e->full_name, $e->department?->name, (int) $stats[$e->id]->h, (int) $stats[$e->id]->t,
                (int) $stats[$e->id]->i, (int) $stats[$e->id]->c, (int) $stats[$e->id]->a, (int) $stats[$e->id]->lm, round($stats[$e->id]->wh, 1)]);

        return ['columns' => ['NIK', 'Nama', 'Departemen', 'Hadir', 'Terlambat', 'Izin/Sakit', 'Cuti', 'Alpha', 'Menit Terlambat', 'Jam Kerja'], 'rows' => $rows->all(), 'subtitle' => $m->translatedFormat('F Y')];
    }

    protected function reportAttLate(): array
    {
        $m = $this->month();
        $rows = Attendance::with('employee.department')->whereIn('employee_id', $this->employeeIds)->where('status', 'terlambat')
            ->whereBetween('attendance_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])
            ->orderBy('attendance_date')->get()
            ->map(fn ($a) => [$a->attendance_date->format('d/m/Y'), $a->employee->employee_no, $a->employee->full_name, $a->employee->department?->name, substr((string) $a->check_in, 0, 5), $a->late_minutes]);

        return ['columns' => ['Tanggal', 'NIK', 'Nama', 'Departemen', 'Jam Masuk', 'Terlambat (menit)'], 'rows' => $rows->all(), 'subtitle' => $m->translatedFormat('F Y')];
    }

    protected function reportAttLeave(): array
    {
        $m = $this->month();
        $rows = LeaveRequest::with(['employee', 'leaveType'])->whereIn('employee_id', $this->employeeIds)
            ->where('start_date', '<=', $m->copy()->endOfMonth())->where('end_date', '>=', $m)->orderBy('start_date')->get()
            ->map(fn ($l) => [$l->employee->employee_no, $l->employee->full_name, $l->leaveType->name, $l->start_date->format('d/m/Y'), $l->end_date->format('d/m/Y'), $l->total_days, $l->status]);

        return ['columns' => ['NIK', 'Nama', 'Jenis', 'Mulai', 'Selesai', 'Hari', 'Status'], 'rows' => $rows->all(), 'subtitle' => $m->translatedFormat('F Y')];
    }

    // ------------------------------------------------------------ Gaji

    protected function reportPayRegister(): array
    {
        $rows = $this->registers()->map(fn ($r) => [$r->snapshot['employee_no'] ?? '', $r->snapshot['full_name'] ?? '', $r->snapshot['department'] ?? '',
            (float) $r->basic_salary, (float) $r->total_allowance, (float) $r->total_deduction, (float) $r->tax_amount, (float) $r->net_salary]);

        return ['columns' => ['NIK', 'Nama', 'Departemen', 'Gaji Pokok', 'Allowance', 'Deduction', 'PPh 21', 'Take Home Pay'], 'rows' => $rows->all(),
            'subtitle' => $this->period()?->name ?? '-', 'money' => [3, 4, 5, 6, 7]];
    }

    protected function reportPayDept(): array
    {
        $rows = $this->registers()->groupBy(fn ($r) => $r->snapshot['department'] ?? '-')->map(fn ($g, $d) => [
            $d, $g->count(), (float) $g->sum('basic_salary'), (float) $g->sum('total_allowance'), (float) $g->sum('total_deduction'), (float) $g->sum('tax_amount'), (float) $g->sum('net_salary'),
        ])->sortKeys()->values();

        return ['columns' => ['Departemen', 'Headcount', 'Gaji Pokok', 'Allowance', 'Deduction', 'PPh 21', 'Take Home Pay'], 'rows' => $rows->all(),
            'subtitle' => $this->period()?->name ?? '-', 'money' => [2, 3, 4, 5, 6]];
    }

    // ------------------------------------------------------------ Allowance / Deduction

    protected function reportCmpRecap(): array
    {
        $period = $this->period();
        $rows = PayrollRegisterItem::with('payComponent')
            ->whereHas('register', fn ($q) => $q->where('payroll_period_id', $period?->id)->whereIn('employee_id', $this->employeeIds))
            ->get()->groupBy(fn ($i) => $i->payComponent?->code ?? '-')
            ->map(fn ($g, $code) => [$code, $g->first()->payComponent?->name, $g->first()->type === 'allowance' ? 'Allowance' : 'Deduction', $g->count(), (float) $g->sum('amount')])
            ->sortKeys()->values();

        return ['columns' => ['Kode', 'Komponen', 'Tipe', 'Penerima', 'Total'], 'rows' => $rows->all(), 'subtitle' => $period?->name ?? '-', 'money' => [4]];
    }

    protected function reportCmpLoan(): array
    {
        $rows = Loan::with('employee')->whereIn('employee_id', $this->employeeIds)->orderByDesc('remaining_amount')->get()
            ->map(fn ($l) => [$l->employee->employee_no, $l->employee->full_name, (float) $l->total_amount, $l->tenor_months, (float) $l->installment_amount, (float) $l->remaining_amount, $l->status]);

        return ['columns' => ['NIK', 'Nama', 'Total', 'Tenor', 'Cicilan', 'Sisa', 'Status'], 'rows' => $rows->all(), 'subtitle' => 'Per '.now()->translatedFormat('d F Y'), 'money' => [2, 4, 5]];
    }

    // ------------------------------------------------------------ Pajak & BPJS

    protected function reportTaxPph21(): array
    {
        $rows = $this->registers()->map(fn ($r) => [$r->snapshot['employee_no'] ?? '', $r->snapshot['full_name'] ?? '', $r->snapshot['npwp'] ?? '-', $r->snapshot['ptkp_status'] ?? '',
            (float) ($r->basic_salary + $r->items->where('type', 'allowance')->filter(fn ($i) => $i->payComponent?->is_taxable)->sum('amount')), (float) $r->tax_amount]);

        return ['columns' => ['NIK', 'Nama', 'NPWP', 'PTKP', 'Bruto Kena Pajak', 'PPh 21 TER'], 'rows' => $rows->all(), 'subtitle' => $this->period()?->name ?? '-', 'money' => [4, 5]];
    }

    protected function reportTaxBpjs(): array
    {
        $rows = $this->registers()->map(function ($r) {
            $kes = $r->items->first(fn ($i) => $i->payComponent?->code === 'DED-BPJS-KES')?->amount ?? 0;
            $tk = $r->items->first(fn ($i) => $i->payComponent?->code === 'DED-BPJS-TK')?->amount ?? 0;

            return [$r->snapshot['employee_no'] ?? '', $r->snapshot['full_name'] ?? '', (float) $kes, (float) $tk, (float) ($kes + $tk)];
        });

        return ['columns' => ['NIK', 'Nama', 'BPJS Kesehatan', 'BPJS TK (JHT+JP)', 'Total'], 'rows' => $rows->all(), 'subtitle' => $this->period()?->name ?? '-', 'money' => [2, 3, 4]];
    }

    // ------------------------------------------------------------ Transfer bank

    protected function reportBankTransfer(): array
    {
        $rows = $this->registers()->sortBy(fn ($r) => ($r->snapshot['bank_name'] ?? '').($r->snapshot['full_name'] ?? ''))->values()
            ->map(fn ($r) => [$r->snapshot['bank_name'] ?? '', $r->snapshot['account_no'] ?? '', $r->snapshot['account_name'] ?? '', $r->snapshot['employee_no'] ?? '', (float) $r->net_salary]);

        return ['columns' => ['Bank', 'No. Rekening', 'Atas Nama', 'NIK', 'Jumlah Transfer'], 'rows' => $rows->all(), 'subtitle' => $this->period()?->name ?? '-', 'money' => [4]];
    }
}
