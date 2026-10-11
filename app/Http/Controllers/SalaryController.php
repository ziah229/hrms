<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalaryController extends Controller
{
    public const TYPES = ['monthly' => 'Bulanan', 'daily' => 'Harian', 'hourly' => 'Per Jam'];

    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            $current = EmployeeSalary::whereNull('end_date')->whereHas('employee', fn ($q) => $q->where('is_active', true));

            return view('salaries.index', [
                'departments' => Department::orderBy('name')->get(),
                'employees' => Employee::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'employee_no']),
                'types' => self::TYPES,
                'totalBasic' => (clone $current)->sum('basic_salary'),
                'avgBasic' => (clone $current)->avg('basic_salary'),
                'changesThisYear' => EmployeeSalary::whereYear('effective_date', now()->year)->where('reason', 'not like', 'Gaji awal%')->count(),
            ]);
        }

        // Mode riwayat: semua baris perubahan terbaru.
        if ($request->input('view') === 'history') {
            return response()->json(EmployeeSalary::with('employee.department')
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
                ->when($request->filled('department_id'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id)))
                ->latest('effective_date')->latest('id')
                ->paginate($request->integer('per_page', 10))
                ->through(fn (EmployeeSalary $s) => [
                    'employee_id' => $s->employee_id,
                    'employee_no' => $s->employee->employee_no,
                    'full_name' => $s->employee->full_name,
                    'department' => $s->employee->department?->name,
                    'basic_salary' => (float) $s->basic_salary,
                    'salary_type' => self::TYPES[$s->salary_type],
                    'effective_date' => $s->effective_date->translatedFormat('d M Y'),
                    'end_date' => $s->end_date?->translatedFormat('d M Y'),
                    'reason' => $s->reason,
                ]));
        }

        $today = now()->toDateString();
        $employees = Employee::with(['department', 'position', 'salaries' => fn ($q) => $q->latest('effective_date')->limit(2)])
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->orderBy('employee_no')
            ->paginate($request->integer('per_page', 10))
            ->through(function (Employee $e) use ($today) {
                $current = $e->salaries->first(fn ($s) => $s->effective_date->toDateString() <= $today);
                $previous = $e->salaries->first(fn ($s) => $current && $s->id !== $current->id);
                $change = $current && $previous && $previous->basic_salary > 0
                    ? round(($current->basic_salary - $previous->basic_salary) / $previous->basic_salary * 100, 1) : null;

                return [
                    'employee_id' => $e->id,
                    'employee_no' => $e->employee_no,
                    'full_name' => $e->full_name,
                    'department' => $e->department?->name,
                    'position' => $e->position?->name,
                    'ptkp_status' => $e->ptkp_status,
                    'basic_salary' => (float) ($current->basic_salary ?? 0),
                    'salary_type' => $current ? self::TYPES[$current->salary_type] : '-',
                    'effective_date' => $current?->effective_date->translatedFormat('d M Y'),
                    'change' => $change,
                ];
            });

        return response()->json($employees);
    }

    public function history(Employee $employee): JsonResponse
    {
        return response()->json([
            'employee' => $employee->only(['id', 'employee_no', 'full_name']),
            'rows' => $employee->salaries()->latest('effective_date')->get()->map(fn ($s) => [
                'basic_salary' => (float) $s->basic_salary,
                'salary_type' => self::TYPES[$s->salary_type],
                'effective_date' => $s->effective_date->translatedFormat('d M Y'),
                'end_date' => $s->end_date?->translatedFormat('d M Y'),
                'reason' => $s->reason,
            ]),
        ]);
    }

    /**
     * Perubahan gaji membuat baris baru; baris aktif sebelumnya ditutup (end_date) — riwayat tidak ditimpa.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'salary_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $latest = EmployeeSalary::where('employee_id', $data['employee_id'])->latest('effective_date')->first();
        if ($latest && $latest->effective_date->toDateString() >= $data['effective_date']) {
            throw ValidationException::withMessages(['effective_date' => 'Tanggal efektif harus setelah '.$latest->effective_date->translatedFormat('d M Y').'.']);
        }

        DB::transaction(function () use ($data, $latest) {
            $latest?->update(['end_date' => Carbon::parse($data['effective_date'])->subDay()->toDateString()]);
            $salary = EmployeeSalary::create($data);
            AuditLog::record('salary.change', $salary, $latest?->only(['basic_salary', 'salary_type', 'effective_date']), $data);
        });

        return response()->json(['message' => 'Perubahan gaji tersimpan dan tercatat di riwayat.']);
    }

    public function export(): BinaryFileResponse
    {
        $today = now()->toDateString();
        $rows = Employee::with(['department', 'position'])->where('is_active', true)->orderBy('employee_no')->get()
            ->map(function (Employee $e) use ($today) {
                $s = $e->salaryAt($today);

                return [$e->employee_no, $e->full_name, $e->department?->name, $e->position?->name, $e->ptkp_status,
                    (float) ($s->basic_salary ?? 0), $s ? self::TYPES[$s->salary_type] : '-', $s?->effective_date->toDateString()];
            })->all();

        AuditLog::record('salary.export');

        return Excel::download(new ArrayExport(['NIK', 'Nama', 'Departemen', 'Jabatan', 'PTKP', 'Gaji Pokok', 'Skema', 'Tgl Efektif'], $rows, 'Gaji Pokok'),
            'gaji-pokok-'.now()->format('Ymd').'.xlsx');
    }
}
