<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OvertimeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = Employee::visibleTo($request->user())->pluck('id');
        $user = $request->user();

        $rows = OvertimeRequest::with(['employee.department', 'approver'])
            ->whereIn('employee_id', $ids)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('full_name', 'like', "%{$request->q}%")->orWhere('employee_no', 'like', "%{$request->q}%")))
            ->orderByRaw("status = 'pending' DESC")
            ->latest('overtime_date')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (OvertimeRequest $o) => [
                'id' => $o->id,
                'employee_no' => $o->employee->employee_no,
                'full_name' => $o->employee->full_name,
                'department' => $o->employee->department?->name,
                'overtime_date' => $o->overtime_date->translatedFormat('d M Y'),
                'hours' => (float) $o->hours,
                'reason' => $o->reason,
                'status' => $o->status,
                'approver' => $o->approver?->name,
                'can_decide' => $o->status === 'pending' && $user->hasPermission('attendance.approve') && $o->employee_id !== $user->employee?->id,
            ]);

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'overtime_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $employeeId = $user->hasPermission('attendance.create') && ! empty($data['employee_id'])
            ? (int) $data['employee_id']
            : $user->employee?->id;
        if (! $employeeId) {
            throw ValidationException::withMessages(['employee_id' => 'Akun Anda belum terhubung dengan data karyawan.']);
        }

        $overtime = OvertimeRequest::create(['employee_id' => $employeeId, 'status' => 'pending'] + $data);
        AuditLog::record('overtime.create', $overtime, null, $data);

        return response()->json(['message' => 'Pengajuan lembur berhasil dikirim.']);
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->guard($request, $overtimeRequest);
        $overtimeRequest->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
        AuditLog::record('overtime.approve', $overtimeRequest);

        return response()->json(['message' => 'Lembur disetujui dan akan dihitung pada register gaji.']);
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->guard($request, $overtimeRequest);
        $overtimeRequest->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);
        AuditLog::record('overtime.reject', $overtimeRequest);

        return response()->json(['message' => 'Pengajuan lembur ditolak.']);
    }

    protected function guard(Request $request, OvertimeRequest $o): void
    {
        $visible = Employee::visibleTo($request->user())->whereKey($o->employee_id)->exists();
        if (! $visible || $o->status !== 'pending' || $o->employee_id === $request->user()->employee?->id) {
            AuditLog::record('access.denied', $o);
            abort(403, 'Anda tidak dapat memproses pengajuan ini.');
        }
    }
}
