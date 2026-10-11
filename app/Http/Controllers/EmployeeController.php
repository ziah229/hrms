<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Http\Requests\EmployeeRequest;
use App\Imports\HeadingRowImport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    public const BANKS = ['BCA', 'Bank Mandiri', 'BNI', 'BRI', 'CIMB Niaga', 'Bank Syariah Indonesia', 'Permata Bank'];

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        if (! $request->expectsJson()) {
            $visible = Employee::visibleTo($user);

            return view('employees.index', [
                'stats' => [
                    'total' => (clone $visible)->count(),
                    'active' => (clone $visible)->where('is_active', true)->count(),
                    'kontrak' => (clone $visible)->where('is_active', true)->where('employment_status', 'kontrak')->count(),
                    'magang' => (clone $visible)->where('is_active', true)->where('employment_status', 'magang')->count(),
                ],
                'departments' => Department::orderBy('name')->get(),
                'positions' => Position::orderBy('name')->get(),
                'locations' => WorkLocation::orderBy('name')->get(),
                'managers' => Employee::where('is_active', true)->whereHas('position', fn ($q) => $q->whereIn('level', ['L1', 'L2', 'L3']))->orderBy('full_name')->get(['id', 'full_name', 'employee_no']),
                'banks' => self::BANKS,
                'nextNo' => $this->nextEmployeeNo(),
            ]);
        }

        $sensitive = $user->hasPermission('employee.update', 'salary.view');

        $employees = $this->filteredQuery($request)
            ->with(['department', 'position', 'primaryBank'])
            ->orderBy('employee_no')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'full_name' => $e->full_name,
                'email' => $e->email,
                'department' => $e->department?->name,
                'position' => $e->position?->name,
                'employment_status' => $e->employment_status,
                'join_date' => $e->join_date?->translatedFormat('d M Y'),
                'bank' => $sensitive && $e->primaryBank ? $e->primaryBank->bank_name.' • '.$e->primaryBank->account_no : null,
                'bank_owner' => $sensitive ? $e->primaryBank?->account_name : null,
                'is_active' => $e->is_active,
                'resign_date' => $e->resign_date?->translatedFormat('d M Y'),
            ]);

        return response()->json($employees);
    }

    public function show(Request $request, Employee $employee): View|JsonResponse
    {
        $this->authorizeVisible($request, $employee);
        $employee->load(['department', 'position', 'workLocation', 'manager', 'primaryBank', 'contracts', 'families', 'documents', 'user.role']);
        $sensitive = $request->user()->hasPermission('employee.update', 'salary.view') || $request->user()->employee?->id === $employee->id;

        if ($request->expectsJson()) {
            $contract = $employee->contracts->sortByDesc('start_date')->first();

            return response()->json(array_merge(
                Arr::except($employee->toArray(), ['primary_bank', 'contracts', 'families', 'documents', 'user', 'department', 'position', 'work_location', 'manager']),
                [
                    'birth_date' => $employee->birth_date?->toDateString(),
                    'join_date' => $employee->join_date?->toDateString(),
                    'contract_end_date' => $contract?->end_date?->toDateString(),
                    'bank_name' => $employee->primaryBank?->bank_name,
                    'account_no' => $sensitive ? $employee->primaryBank?->account_no : null,
                    'account_name' => $employee->primaryBank?->account_name,
                    'npwp' => $sensitive ? $employee->npwp : null,
                ]
            ));
        }

        return view('employees.show', [
            'employee' => $employee,
            'sensitive' => $sensitive,
            'salary' => $request->user()->hasPermission('salary.view') ? $employee->salaryAt(now()->toDateString()) : null,
        ]);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $employee = DB::transaction(function () use ($request) {
            $employee = Employee::create($this->employeeData($request) + ['employee_no' => $this->nextEmployeeNo(), 'is_active' => true]);
            $this->syncRelations($employee, $request);

            return $employee;
        });

        AuditLog::record('employee.create', $employee, null, $employee->only(['employee_no', 'full_name', 'department_id', 'position_id']));

        return response()->json(['message' => "Karyawan {$employee->full_name} ({$employee->employee_no}) berhasil ditambahkan.", 'id' => $employee->id]);
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $old = $employee->only(array_keys($this->employeeData($request)));

        DB::transaction(function () use ($request, $employee) {
            $employee->update($this->employeeData($request));
            $this->syncRelations($employee, $request);
        });

        AuditLog::record('employee.update', $employee, $old, $employee->only(array_keys($old)));

        return response()->json(['message' => "Data {$employee->full_name} berhasil diperbarui."]);
    }

    public function resign(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate(['resign_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255']]);
        $employee->update(['is_active' => false, 'resign_date' => $data['resign_date']]);
        $employee->user?->update(['is_active' => false]);

        AuditLog::record('employee.resign', $employee, ['is_active' => true], $data);

        return response()->json(['message' => "{$employee->full_name} dinonaktifkan (resign)."]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();
        AuditLog::record('employee.delete', $employee, $employee->only(['employee_no', 'full_name']));

        return response()->json(['message' => 'Data karyawan dihapus.']);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->filteredQuery($request)->with(['department', 'position', 'workLocation', 'primaryBank'])->orderBy('employee_no')->get()
            ->map(fn (Employee $e) => [
                $e->employee_no, $e->full_name, $e->nik_ktp, $e->npwp, $e->gender, $e->birth_place, $e->birth_date?->toDateString(),
                $e->email, $e->phone, $e->department?->name, $e->position?->name, $e->workLocation?->name,
                Employee::EMPLOYMENT_STATUSES[$e->employment_status] ?? $e->employment_status, $e->join_date?->toDateString(),
                $e->ptkp_status, $e->primaryBank?->bank_name, $e->primaryBank?->account_no, $e->is_active ? 'Aktif' : 'Nonaktif',
            ])->all();

        AuditLog::record('employee.export', null, null, $request->only(['q', 'department_id', 'status']));

        return Excel::download(new ArrayExport([
            'NIK Karyawan', 'Nama', 'KTP', 'NPWP', 'JK', 'Tempat Lahir', 'Tgl Lahir', 'Email', 'Telepon', 'Departemen',
            'Jabatan', 'Lokasi', 'Status Kerja', 'Tgl Masuk', 'PTKP', 'Bank', 'No. Rekening', 'Status',
        ], $rows, 'Karyawan'), 'data-karyawan-'.now()->format('Ymd').'.xlsx');
    }

    /**
     * Import XLSX. Kolom: nama, ktp, email, telepon, kode_departemen, kode_jabatan, status_kerja, tanggal_masuk, ptkp, bank, no_rekening
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']]);
        $rows = Excel::toCollection(new HeadingRowImport, $request->file('file'))->first();

        $departments = Department::pluck('id', 'code');
        $positions = Position::pluck('id', 'code');
        $created = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $departments, $positions, &$created, &$errors) {
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                if (blank($row['nama'] ?? null)) {
                    continue;
                }
                if (! isset($departments[$row['kode_departemen'] ?? '']) || ! isset($positions[$row['kode_jabatan'] ?? ''])) {
                    $errors[] = "Baris {$line}: kode departemen/jabatan tidak dikenal.";

                    continue;
                }
                $employee = Employee::create([
                    'employee_no' => $this->nextEmployeeNo(),
                    'full_name' => $row['nama'],
                    'nik_ktp' => (string) ($row['ktp'] ?? ''),
                    'email' => $row['email'] ?? null,
                    'phone' => (string) ($row['telepon'] ?? ''),
                    'department_id' => $departments[$row['kode_departemen']],
                    'position_id' => $positions[$row['kode_jabatan']],
                    'employment_status' => in_array($row['status_kerja'] ?? '', ['tetap', 'kontrak', 'magang']) ? $row['status_kerja'] : 'tetap',
                    'join_date' => $this->excelDate($row['tanggal_masuk'] ?? null) ?? now()->toDateString(),
                    'ptkp_status' => $row['ptkp'] ?? 'TK/0',
                    'is_active' => true,
                ]);
                if (filled($row['no_rekening'] ?? null)) {
                    $employee->bankAccounts()->create([
                        'bank_name' => $row['bank'] ?? 'BCA', 'account_no' => (string) $row['no_rekening'],
                        'account_name' => $employee->full_name, 'is_primary' => true,
                    ]);
                }
                $created++;
            }
        });

        AuditLog::record('employee.import', null, null, ['created' => $created, 'errors' => count($errors)]);

        return response()->json([
            'message' => "{$created} karyawan berhasil diimpor.".($errors ? ' '.count($errors).' baris dilewati.' : ''),
            'errors_detail' => $errors,
        ]);
    }

    protected function filteredQuery(Request $request)
    {
        return Employee::visibleTo($request->user())
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', '%'.$request->q.'%')
                ->orWhere('employee_no', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->position_id))
            ->when($request->filled('status'), fn ($q) => $q->where('employment_status', $request->status))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')));
    }

    protected function authorizeVisible(Request $request, Employee $employee): void
    {
        if (! Employee::visibleTo($request->user())->whereKey($employee->id)->exists()) {
            AuditLog::record('access.denied', $employee);
            abort(403, 'Data karyawan di luar cakupan akses Anda.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function employeeData(EmployeeRequest $request): array
    {
        $data = Arr::except($request->validated(), ['contract_end_date', 'bank_name', 'account_no', 'account_name', 'photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('employees/photos', 'public');
        }

        return $data;
    }

    protected function syncRelations(Employee $employee, EmployeeRequest $request): void
    {
        $employee->bankAccounts()->updateOrCreate(['is_primary' => true], [
            'bank_name' => $request->bank_name,
            'account_no' => $request->account_no,
            'account_name' => $request->account_name ?: $employee->full_name,
        ]);

        if (in_array($request->employment_status, ['kontrak', 'magang'])) {
            $employee->contracts()->updateOrCreate(['start_date' => $request->join_date], [
                'contract_type' => $request->employment_status === 'kontrak' ? 'PKWT' : 'Magang',
                'end_date' => $request->contract_end_date,
            ]);
        }
    }

    protected function nextEmployeeNo(): string
    {
        $prefix = 'EMP-'.now()->year.'-';
        $last = Employee::withTrashed()->where('employee_no', 'like', $prefix.'%')->orderByDesc('employee_no')->value('employee_no');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function excelDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => "Format tanggal tidak valid: {$value}"]);
        }
    }
}
