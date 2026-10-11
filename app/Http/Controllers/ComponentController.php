<?php

namespace App\Http\Controllers;

use App\Models\AdhocComponent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\Loan;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComponentController extends Controller
{
    // ---------------------------------------------------------------- Master komponen

    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            $active = PayComponent::where('is_active', true);

            return view('pay-components.index', [
                'stats' => [
                    'active' => (clone $active)->count(),
                    'allowance' => (clone $active)->where('type', 'allowance')->count(),
                    'deduction' => (clone $active)->where('type', 'deduction')->count(),
                    'taxable' => (clone $active)->where('is_taxable', true)->count(),
                    'monthly' => EmployeeComponent::whereNull('end_date')->whereHas('payComponent', fn ($q) => $q->where('calc_method', 'nominal')->where('type', 'allowance'))->sum('value'),
                    'assignments' => EmployeeComponent::whereNull('end_date')->count(),
                    'loans' => Loan::where('status', 'active')->sum('remaining_amount'),
                    'loanCount' => Loan::where('status', 'active')->count(),
                ],
                'counts' => [
                    'master' => PayComponent::count(),
                    'employee' => EmployeeComponent::count(),
                    'adhoc' => AdhocComponent::count(),
                    'loan' => Loan::where('status', 'active')->count(),
                ],
                'components' => PayComponent::where('is_active', true)->orderBy('type')->orderBy('code')->get(),
                'employees' => Employee::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'employee_no']),
                'periods' => PayrollPeriod::whereNot('status', 'final')->orderByDesc('year')->orderByDesc('month')->get(),
                'methods' => PayComponent::CALC_METHODS,
            ]);
        }

        $rows = PayComponent::withCount(['employeeComponents as employee_count' => fn ($q) => $q->whereNull('end_date')])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$request->q}%")->orWhere('name', 'like', "%{$request->q}%")))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('nature'), fn ($q) => $q->where('nature', $request->nature))
            ->when($request->filled('taxable'), fn ($q) => $q->where('is_taxable', $request->boolean('taxable')))
            ->orderBy('type')->orderBy('code')
            ->paginate($request->integer('per_page', 10));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $component = PayComponent::create($this->validateComponent($request));
        AuditLog::record('component.create', $component, null, $component->toArray());

        return response()->json(['message' => "Komponen {$component->code} berhasil ditambahkan."]);
    }

    public function update(Request $request, PayComponent $payComponent): JsonResponse
    {
        $data = $this->validateComponent($request, $payComponent);
        $old = $payComponent->only(array_keys($data));
        $payComponent->update($data);
        AuditLog::record('component.update', $payComponent, $old, $data);

        return response()->json(['message' => "Komponen {$payComponent->code} diperbarui."]);
    }

    /**
     * Komponen yang sudah dipakai tidak dihapus, hanya dinonaktifkan.
     */
    public function destroy(PayComponent $payComponent): JsonResponse
    {
        $used = $payComponent->employeeComponents()->exists() || $payComponent->adhocComponents()->exists()
            || \App\Models\PayrollRegisterItem::where('pay_component_id', $payComponent->id)->exists();

        if ($used) {
            $payComponent->update(['is_active' => false]);
            AuditLog::record('component.deactivate', $payComponent);

            return response()->json(['message' => 'Komponen sudah dipakai sehingga dinonaktifkan (tidak dihapus).']);
        }

        $payComponent->delete();
        AuditLog::record('component.delete', $payComponent, $payComponent->toArray());

        return response()->json(['message' => 'Komponen dihapus.']);
    }

    // ---------------------------------------------------------------- Komponen per karyawan

    public function employeeIndex(Request $request): JsonResponse
    {
        $rows = EmployeeComponent::with(['employee.department', 'payComponent'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
            ->when($request->filled('pay_component_id'), fn ($q) => $q->where('pay_component_id', $request->pay_component_id))
            ->when($request->input('active', '1') === '1', fn ($q) => $q->where(fn ($w) => $w->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString())))
            ->orderBy('employee_id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (EmployeeComponent $c) => [
                'id' => $c->id,
                'employee_id' => $c->employee_id,
                'employee_no' => $c->employee->employee_no,
                'full_name' => $c->employee->full_name,
                'department' => $c->employee->department?->name,
                'pay_component_id' => $c->pay_component_id,
                'code' => $c->payComponent->code,
                'component' => $c->payComponent->name,
                'type' => $c->payComponent->type,
                'calc_method' => $c->payComponent->calc_method,
                'value' => (float) $c->value,
                'start_date' => $c->start_date->toDateString(),
                'end_date' => $c->end_date?->toDateString(),
            ]);

        return response()->json($rows);
    }

    public function employeeStore(Request $request): JsonResponse
    {
        $data = $this->validateAssignment($request);
        $exists = EmployeeComponent::where('employee_id', $data['employee_id'])->where('pay_component_id', $data['pay_component_id'])
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $data['start_date']))->exists();
        if ($exists) {
            throw ValidationException::withMessages(['pay_component_id' => 'Komponen ini masih aktif untuk karyawan tersebut. Edit atau akhiri yang lama.']);
        }

        $assignment = EmployeeComponent::create($data);
        AuditLog::record('component.assign', $assignment, null, $data);

        return response()->json(['message' => 'Komponen berhasil di-assign ke karyawan.']);
    }

    public function employeeUpdate(Request $request, EmployeeComponent $employeeComponent): JsonResponse
    {
        $data = $this->validateAssignment($request);
        $old = $employeeComponent->only(array_keys($data));
        $employeeComponent->update($data);
        AuditLog::record('component.assign.update', $employeeComponent, $old, $data);

        return response()->json(['message' => 'Komponen karyawan diperbarui.']);
    }

    public function employeeDestroy(EmployeeComponent $employeeComponent): JsonResponse
    {
        $employeeComponent->delete();
        AuditLog::record('component.assign.delete', $employeeComponent, $employeeComponent->toArray());

        return response()->json(['message' => 'Komponen karyawan dihapus.']);
    }

    // ---------------------------------------------------------------- Sekali bayar

    public function adhocIndex(Request $request): JsonResponse
    {
        $rows = AdhocComponent::with(['employee', 'payComponent', 'payrollPeriod'])
            ->when($request->filled('payroll_period_id'), fn ($q) => $q->where('payroll_period_id', $request->payroll_period_id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$request->q}%")))
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->through(fn (AdhocComponent $a) => [
                'id' => $a->id,
                'employee_no' => $a->employee->employee_no,
                'full_name' => $a->employee->full_name,
                'code' => $a->payComponent->code,
                'component' => $a->payComponent->name,
                'type' => $a->payComponent->type,
                'period' => $a->payrollPeriod->name,
                'period_locked' => $a->payrollPeriod->isLocked(),
                'amount' => (float) $a->amount,
                'notes' => $a->notes,
            ]);

        return response()->json($rows);
    }

    public function adhocStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'pay_component_id' => ['required', 'exists:pay_components,id'],
            'payroll_period_id' => ['required', Rule::exists('payroll_periods', 'id')->whereNot('status', 'final')],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['payroll_period_id.exists' => 'Periode tidak valid atau sudah final.']);

        $adhoc = AdhocComponent::create($data);
        AuditLog::record('component.adhoc.create', $adhoc, null, $data);

        return response()->json(['message' => 'Komponen sekali bayar tersimpan. Proses ulang register gaji agar terhitung.']);
    }

    public function adhocDestroy(AdhocComponent $adhocComponent): JsonResponse
    {
        if ($adhocComponent->payrollPeriod->isLocked()) {
            abort(422, 'Periode sudah final, data tidak dapat dihapus.');
        }
        $adhocComponent->delete();
        AuditLog::record('component.adhoc.delete', $adhocComponent, $adhocComponent->toArray());

        return response()->json(['message' => 'Komponen sekali bayar dihapus.']);
    }

    // ---------------------------------------------------------------- Kasbon / pinjaman

    public function loanIndex(Request $request): JsonResponse
    {
        $rows = Loan::with('employee')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$request->q}%")))
            ->orderByRaw("status = 'active' DESC")->latest('start_date')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Loan $l) => [
                'id' => $l->id,
                'employee_no' => $l->employee->employee_no,
                'full_name' => $l->employee->full_name,
                'total_amount' => (float) $l->total_amount,
                'installment_amount' => (float) $l->installment_amount,
                'remaining_amount' => (float) $l->remaining_amount,
                'tenor_months' => $l->tenor_months,
                'paid_count' => $l->installments()->where('status', 'paid')->count(),
                'start_date' => $l->start_date->translatedFormat('M Y'),
                'status' => $l->status,
            ]);

        return response()->json($rows);
    }

    public function loanStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'total_amount' => ['required', 'numeric', 'min:100000'],
            'tenor_months' => ['required', 'integer', 'between:1,60'],
            'start_date' => ['required', 'date'],
        ]);

        $loan = Loan::create($data + [
            'installment_amount' => ceil($data['total_amount'] / $data['tenor_months']),
            'remaining_amount' => $data['total_amount'],
            'status' => 'active',
        ]);
        AuditLog::record('loan.create', $loan, null, $data);

        return response()->json(['message' => 'Kasbon tercatat; cicilan otomatis menjadi potongan di register gaji.']);
    }

    // ---------------------------------------------------------------- Validasi

    /**
     * @return array<string, mixed>
     */
    protected function validateComponent(Request $request, ?PayComponent $component = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('pay_components', 'code')->ignore($component?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['allowance', 'deduction'])],
            'nature' => ['required', Rule::in(['fixed', 'variable'])],
            'calc_method' => ['required', Rule::in(array_keys(PayComponent::CALC_METHODS))],
            'default_value' => ['required', 'numeric', 'min:0'],
            'formula' => ['nullable', 'string', 'max:255'],
            'is_taxable' => ['boolean'],
            'is_active' => ['boolean'],
        ], ['code.regex' => 'Kode hanya huruf kapital, angka, dan tanda minus (contoh: ALW-TRN).']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateAssignment(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'pay_component_id' => ['required', 'exists:pay_components,id'],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }
}
