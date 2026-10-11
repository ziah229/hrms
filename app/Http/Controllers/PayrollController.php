<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Models\PayrollRegister;
use App\Models\PayrollRegisterItem;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $service) {}

    public function index(Request $request): View
    {
        $periods = PayrollPeriod::orderByDesc('year')->orderByDesc('month')->get();
        $period = $request->filled('period')
            ? $periods->firstWhere('id', $request->integer('period'))
            : ($periods->firstWhere('status', '!=', 'draft') ?? $periods->first());

        $summary = $period ? PayrollRegister::where('payroll_period_id', $period->id)->selectRaw('
            COUNT(*) as headcount, SUM(basic_salary) as basic, SUM(total_allowance) as allowance,
            SUM(total_deduction) as deduction, SUM(tax_amount) as tax, SUM(net_salary) as net,
            SUM(overtime_hours) as overtime_hours, SUM(late_count) as late_count, SUM(status = \'review\') as review_count')->first() : null;

        $itemTotals = $period ? PayrollRegisterItem::whereHas('register', fn ($q) => $q->where('payroll_period_id', $period->id))
            ->join('pay_components', 'pay_components.id', '=', 'payroll_register_items.pay_component_id')
            ->selectRaw('pay_components.code, SUM(payroll_register_items.amount) as total')
            ->groupBy('pay_components.code')->pluck('total', 'code') : collect();

        return view('payroll.index', [
            'periods' => $periods,
            'period' => $period,
            'summary' => $summary,
            'itemTotals' => $itemTotals,
            'departments' => Department::orderBy('name')->get(),
            'audits' => $period ? AuditLog::with('user')->where('entity', 'PayrollPeriod')->where('entity_id', $period->id)->latest('id')->limit(6)->get() : collect(),
        ]);
    }

    public function registers(Request $request, PayrollPeriod $period): JsonResponse
    {
        $rows = PayrollRegister::with('employee.department')
            ->where('payroll_period_id', $period->id)
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('employee_id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (PayrollRegister $r) => [
                'id' => $r->id,
                'employee_no' => $r->snapshot['employee_no'] ?? $r->employee->employee_no,
                'full_name' => $r->snapshot['full_name'] ?? $r->employee->full_name,
                'position' => $r->snapshot['position'] ?? null,
                'department' => $r->snapshot['department'] ?? $r->employee->department?->name,
                'basic_salary' => (float) $r->basic_salary,
                'total_allowance' => (float) $r->total_allowance,
                'total_deduction' => (float) $r->total_deduction,
                'tax_amount' => (float) $r->tax_amount,
                'net_salary' => (float) $r->net_salary,
                'status' => $r->status,
            ]);

        return response()->json($rows);
    }

    public function showRegister(PayrollRegister $register): JsonResponse
    {
        $register->load(['items', 'period']);

        return response()->json([
            'register' => $register->only(['id', 'basic_salary', 'total_allowance', 'total_deduction', 'tax_amount', 'net_salary',
                'present_days', 'absent_days', 'late_count', 'overtime_hours', 'status', 'snapshot']),
            'period' => $register->period->name,
            'allowances' => $register->items->where('type', 'allowance')->values()->map->only(['description', 'amount']),
            'deductions' => $register->items->where('type', 'deduction')->values()->map->only(['description', 'amount']),
        ]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100', Rule::unique('payroll_periods')->where('month', $request->month)],
            'pay_date' => ['nullable', 'date'],
        ], ['year.unique' => 'Periode untuk bulan tersebut sudah ada.']);

        $start = Carbon::create($data['year'], $data['month'], 1);
        $period = PayrollPeriod::create([
            'name' => $start->translatedFormat('F Y'),
            'month' => $data['month'],
            'year' => $data['year'],
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'pay_date' => $data['pay_date'] ?? $start->copy()->setDay(28)->toDateString(),
            'status' => 'draft',
        ]);
        AuditLog::record('payroll.period.create', $period, null, $data);

        return response()->json(['message' => "Periode {$period->name} dibuat.", 'redirect' => route('payroll.index', ['period' => $period->id])]);
    }

    public function process(PayrollPeriod $period): JsonResponse
    {
        $count = $this->service->process($period);

        return response()->json(['message' => "Register gaji {$period->name} dihitung untuk {$count} karyawan.", 'reload' => true]);
    }

    public function approve(PayrollPeriod $period): JsonResponse
    {
        $this->service->approve($period);

        return response()->json(['message' => "Register {$period->name} disetujui.", 'reload' => true]);
    }

    public function finalize(PayrollPeriod $period): JsonResponse
    {
        $this->service->finalize($period);

        return response()->json(['message' => "Periode {$period->name} dikunci (final). Payslip telah digenerate.", 'reload' => true]);
    }

    public function reopen(Request $request, PayrollPeriod $period): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->reopen($period, $data['reason']);

        return response()->json(['message' => "Periode {$period->name} dibuka kembali.", 'reload' => true]);
    }

    public function export(PayrollPeriod $period, string $format): Response
    {
        $registers = PayrollRegister::with('employee')->where('payroll_period_id', $period->id)->orderBy('employee_id')->get();
        AuditLog::record("payroll.export.{$format}", $period);

        if ($format === 'pdf') {
            return Pdf::loadView('payroll.pdf', ['period' => $period, 'registers' => $registers])
                ->setPaper('a4', 'landscape')
                ->download("register-gaji-{$period->year}-{$period->month}.pdf");
        }

        $rows = $registers->map(fn ($r) => [
            $r->snapshot['employee_no'] ?? '', $r->snapshot['full_name'] ?? '', $r->snapshot['department'] ?? '',
            $r->snapshot['bank_name'] ?? '', $r->snapshot['account_no'] ?? '',
            (float) $r->basic_salary, (float) $r->total_allowance, (float) $r->total_deduction, (float) $r->tax_amount, (float) $r->net_salary,
            $r->present_days, $r->absent_days, $r->late_count, (float) $r->overtime_hours, $r->status,
        ])->all();

        return Excel::download(new ArrayExport([
            'NIK', 'Nama', 'Departemen', 'Bank', 'No. Rekening', 'Gaji Pokok', 'Total Allowance', 'Total Deduction',
            'PPh 21', 'Take Home Pay', 'Hadir', 'Alpha', 'Terlambat', 'Jam Lembur', 'Status',
        ], $rows, 'Register '.$period->name), "register-gaji-{$period->year}-{$period->month}.xlsx");
    }
}
