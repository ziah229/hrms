<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pengajuan cuti/izin dengan alur approval: atasan (scope subordinate) -> HR (scope all).
 */
class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = Employee::visibleTo($request->user())->pluck('id');

        $rows = LeaveRequest::with(['employee.department', 'leaveType', 'approver'])
            ->whereIn('employee_id', $ids)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
            ->orderByRaw("FIELD(status, 'pending', 'approved_manager') DESC")
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->through(fn (LeaveRequest $l) => [
                'id' => $l->id,
                'employee_no' => $l->employee->employee_no,
                'full_name' => $l->employee->full_name,
                'department' => $l->employee->department?->name,
                'leave_type' => $l->leaveType->name,
                'start_date' => $l->start_date->translatedFormat('d M Y'),
                'end_date' => $l->end_date->translatedFormat('d M Y'),
                'total_days' => $l->total_days,
                'reason' => $l->reason,
                'status' => $l->status,
                'approver' => $l->approver?->name,
                'can_decide' => $this->canDecide($request, $l),
            ]);

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Hanya pengguna dengan attendance.create yang boleh mengajukan atas nama orang lain.
        $employeeId = $user->hasPermission('attendance.create') && ! empty($data['employee_id'])
            ? (int) $data['employee_id']
            : $user->employee?->id;
        if (! $employeeId) {
            throw ValidationException::withMessages(['employee_id' => 'Akun Anda belum terhubung dengan data karyawan.']);
        }

        $days = collect(CarbonPeriod::create($data['start_date'], $data['end_date']))->reject->isWeekend()->count();
        $overlap = LeaveRequest::where('employee_id', $employeeId)->whereNot('status', 'rejected')
            ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'Sudah ada pengajuan pada rentang tanggal tersebut.']);
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employeeId,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $days,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
        AuditLog::record('leave.create', $leave, null, $data);

        return response()->json(['message' => "Pengajuan {$days} hari kerja berhasil dikirim dan menunggu approval."]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, AttendanceService $attendance): JsonResponse
    {
        $this->guard($request, $leaveRequest);
        $isHr = $request->user()->role?->data_scope === 'all' || $request->user()->isSuperAdmin();
        $status = $isHr ? 'approved' : 'approved_manager';

        $leaveRequest->update(['status' => $status, 'approved_by' => $request->user()->id, 'approved_at' => now()]);

        if ($status === 'approved') {
            $type = strtolower($leaveRequest->leaveType->name);
            $mark = str_contains($type, 'sakit') ? 'sakit' : (str_contains($type, 'izin') ? 'izin' : 'cuti');
            $attendance->markRange($leaveRequest->employee_id, Carbon::parse($leaveRequest->start_date), Carbon::parse($leaveRequest->end_date), $mark, $leaveRequest->leaveType->name);
        }
        AuditLog::record('leave.approve', $leaveRequest, null, ['status' => $status]);

        return response()->json(['message' => $status === 'approved' ? 'Pengajuan disetujui HR (final).' : 'Disetujui atasan, diteruskan ke HR.']);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->guard($request, $leaveRequest);
        $leaveRequest->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        AuditLog::record('leave.reject', $leaveRequest);

        return response()->json(['message' => 'Pengajuan ditolak.']);
    }

    protected function canDecide(Request $request, LeaveRequest $l): bool
    {
        $user = $request->user();
        if (! $user->hasPermission('attendance.approve') || $l->employee_id === $user->employee?->id) {
            return false;
        }
        $isHr = $user->role?->data_scope === 'all' || $user->isSuperAdmin();

        return $l->status === 'pending' || ($isHr && $l->status === 'approved_manager');
    }

    protected function guard(Request $request, LeaveRequest $l): void
    {
        $visible = Employee::visibleTo($request->user())->whereKey($l->employee_id)->exists();
        if (! $visible || ! $this->canDecide($request, $l)) {
            AuditLog::record('access.denied', $l);
            abort(403, 'Anda tidak dapat memproses pengajuan ini.');
        }
    }
}
