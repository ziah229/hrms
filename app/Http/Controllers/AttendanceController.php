<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\HeadingRowImport;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Shift;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $visibleIds = Employee::visibleTo($user)->pluck('id');

        if ($request->expectsJson()) {
            $rows = Attendance::with(['employee.department', 'shift'])
                ->whereIn('employee_id', $visibleIds)
                ->whereDate('attendance_date', $date)
                ->when($request->filled('department_id'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->department_id)))
                ->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->shift_id))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e
                    ->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
                ->orderByRaw("FIELD(status, 'terlambat', 'alpha') DESC")
                ->orderBy('check_in')
                ->paginate($request->integer('per_page', 10))
                ->through(fn (Attendance $a) => [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_no' => $a->employee->employee_no,
                    'full_name' => $a->employee->full_name,
                    'department' => $a->employee->department?->name,
                    'shift' => $a->shift?->label(),
                    'shift_id' => $a->shift_id,
                    'date' => $a->attendance_date->toDateString(),
                    'check_in' => $a->check_in ? substr($a->check_in, 0, 5) : null,
                    'check_out' => $a->check_out ? substr($a->check_out, 0, 5) : null,
                    'late_minutes' => $a->late_minutes,
                    'work_hours' => (float) $a->work_hours,
                    'status' => $a->status,
                    'source' => $a->source,
                    'notes' => $a->notes,
                ]);

            return response()->json($rows);
        }

        $stats = Attendance::whereIn('employee_id', $visibleIds)->whereDate('attendance_date', $date)
            ->selectRaw('status, count(*) as total, avg(nullif(late_minutes, 0)) as avg_late')
            ->groupBy('status')->get()->keyBy('status');

        return view('attendance.index', [
            'date' => $date,
            'stats' => $stats,
            'activeCount' => Employee::whereIn('id', $visibleIds)->where('is_active', true)->count(),
            'pendingLeave' => LeaveRequest::whereIn('employee_id', $visibleIds)->whereIn('status', ['pending', 'approved_manager'])->count(),
            'pendingOvertime' => OvertimeRequest::whereIn('employee_id', $visibleIds)->where('status', 'pending')->count(),
            'departments' => Department::orderBy('name')->get(),
            'shifts' => Shift::orderBy('start_time')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'employees' => Employee::whereIn('id', $visibleIds)->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'employee_no']),
            'self' => $user->employee,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today',
                Rule::unique('attendances')->where('employee_id', $request->employee_id)],
            'shift_id' => ['required', 'exists:shifts,id'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['attendance_date.unique' => 'Karyawan sudah memiliki data absensi pada tanggal tersebut.']);

        $attendance = Attendance::create(array_merge(
            $data,
            $this->service->compute(Shift::find($data['shift_id']), $data['check_in'] ?? null, $data['check_out'] ?? null, $data['status']),
            ['source' => 'manual', 'corrected_by' => $request->user()->id],
        ));

        AuditLog::record('attendance.create', $attendance, null, $data);

        return response()->json(['message' => 'Data absensi berhasil ditambahkan.']);
    }

    /**
     * Koreksi absensi: wajib alasan, tercatat siapa yang mengoreksi & nilai lama di audit log.
     */
    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'exists:shifts,id'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'notes' => ['required', 'string', 'max:255'],
        ], ['notes.required' => 'Alasan koreksi wajib diisi.']);

        $old = $attendance->only(['check_in', 'check_out', 'status', 'late_minutes', 'notes']);
        $attendance->update(array_merge(
            $data,
            $this->service->compute(Shift::find($data['shift_id']), $data['check_in'] ?? null, $data['check_out'] ?? null, $data['status']),
            ['source' => 'manual', 'corrected_by' => $request->user()->id],
        ));

        AuditLog::record('attendance.correct', $attendance, $old, $data);

        return response()->json(['message' => 'Koreksi absensi tersimpan.']);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $month = Carbon::parse($request->input('month', now()->format('Y-m')).'-01');
        $ids = Employee::visibleTo($request->user())->pluck('id');

        $rows = Employee::with('department')->whereIn('id', $ids)->orderBy('employee_no')->get()
            ->map(function (Employee $e) use ($month) {
                $s = Attendance::where('employee_id', $e->id)
                    ->whereBetween('attendance_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->selectRaw("SUM(status='hadir') h, SUM(status='terlambat') t, SUM(status='izin') i, SUM(status='sakit') s,
                        SUM(status='cuti') c, SUM(status='alpha') a, SUM(status='dinas') d, SUM(late_minutes) lm, SUM(work_hours) wh")
                    ->first();

                return [$e->employee_no, $e->full_name, $e->department?->name, (int) $s->h, (int) $s->t, (int) $s->i, (int) $s->s,
                    (int) $s->c, (int) $s->a, (int) $s->d, (int) $s->lm, (float) $s->wh];
            })->all();

        AuditLog::record('attendance.export', null, null, ['month' => $month->format('Y-m')]);

        return Excel::download(new ArrayExport(
            ['NIK', 'Nama', 'Departemen', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Cuti', 'Alpha', 'Dinas', 'Total Menit Terlambat', 'Total Jam Kerja'],
            $rows, 'Rekap '.$month->format('Y-m')
        ), 'rekap-absensi-'.$month->format('Y-m').'.xlsx');
    }

    /**
     * Import log mesin fingerprint / XLSX. Kolom: nik_karyawan, tanggal, jam_masuk, jam_pulang
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'shift_id' => ['required', 'exists:shifts,id'],
        ]);
        $shift = Shift::find($request->shift_id);
        $employees = Employee::pluck('id', 'employee_no');
        $rows = Excel::toCollection(new HeadingRowImport, $request->file('file'))->first();
        $count = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $employees, $shift, &$count, &$skipped) {
            foreach ($rows as $i => $row) {
                $id = $employees[$row['nik_karyawan'] ?? ''] ?? null;
                if (! $id || blank($row['tanggal'] ?? null)) {
                    $skipped[] = 'Baris '.($i + 2).': NIK tidak dikenal / tanggal kosong.';

                    continue;
                }
                $date = is_numeric($row['tanggal'])
                    ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['tanggal'])->format('Y-m-d')
                    : Carbon::parse($row['tanggal'])->toDateString();
                $in = $this->timeValue($row['jam_masuk'] ?? null);
                $out = $this->timeValue($row['jam_pulang'] ?? null);

                Attendance::updateOrCreate(
                    ['employee_id' => $id, 'attendance_date' => $date],
                    ['shift_id' => $shift->id, 'check_in' => $in, 'check_out' => $out, 'source' => 'import']
                        + $this->service->compute($shift, $in, $out)
                );
                $count++;
            }
        });

        AuditLog::record('attendance.import', null, null, ['rows' => $count]);

        return response()->json(['message' => "{$count} baris absensi berhasil diimpor.", 'errors_detail' => $skipped]);
    }

    protected function timeValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('H:i');
        }

        return Carbon::parse($value)->format('H:i');
    }
}
