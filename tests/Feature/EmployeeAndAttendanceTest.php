<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_hr_can_create_employee_via_ajax_and_validation_returns_422(): void
    {
        $this->actingAs(User::where('email', 'hr@hcms.test')->first());

        $this->postJson('/employees', [])->assertStatus(422)->assertJsonValidationErrors(['full_name', 'nik_ktp', 'email', 'phone', 'department_id']);

        $this->postJson('/employees', [
            'full_name' => 'Budi Santoso', 'nik_ktp' => '3271000000000001', 'gender' => 'L', 'ptkp_status' => 'TK/0',
            'email' => 'budi.santoso@nexa.co.id', 'phone' => '081234567890',
            'department_id' => Department::first()->id, 'position_id' => Position::first()->id,
            'employment_status' => 'kontrak', 'join_date' => '2026-10-01', 'contract_end_date' => '2027-09-30',
            'bank_name' => 'BCA', 'account_no' => '1234567890',
        ])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'EMP-2026-'));

        $employee = Employee::where('email', 'budi.santoso@nexa.co.id')->firstOrFail();
        $this->assertSame('1234567890', $employee->primaryBank->account_no);
        $this->assertSame('2027-09-30', $employee->contracts()->first()->end_date->toDateString());
    }

    public function test_payroll_officer_cannot_create_employee(): void
    {
        $this->actingAs(User::where('email', 'payroll@hcms.test')->first())->postJson('/employees', [])->assertForbidden();
    }

    public function test_attendance_is_unique_per_employee_and_date(): void
    {
        $this->actingAs(User::where('email', 'hr@hcms.test')->first());
        $existing = Attendance::first();

        $this->postJson('/attendance', [
            'employee_id' => $existing->employee_id, 'attendance_date' => $existing->attendance_date->toDateString(),
            'shift_id' => Shift::first()->id, 'check_in' => '08:00', 'status' => 'hadir',
        ])->assertStatus(422)->assertJsonValidationErrors('attendance_date');
    }

    public function test_correction_requires_reason_and_recomputes_lateness(): void
    {
        $this->actingAs(User::where('email', 'hr@hcms.test')->first());
        $attendance = Attendance::whereNotNull('check_in')->first();
        $shift = Shift::where('name', 'Shift Pagi')->first();

        $this->putJson("/attendance/{$attendance->id}", ['shift_id' => $shift->id, 'check_in' => '08:30', 'check_out' => '17:30', 'status' => 'hadir'])
            ->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->putJson("/attendance/{$attendance->id}", ['shift_id' => $shift->id, 'check_in' => '08:30', 'check_out' => '17:30', 'status' => 'hadir', 'notes' => 'Lupa tap'])
            ->assertOk();

        $attendance->refresh();
        $this->assertSame('terlambat', $attendance->status);
        $this->assertSame(30, $attendance->late_minutes);
    }

    public function test_leave_approval_flows_manager_then_hr(): void
    {
        $employeeUser = User::where('email', 'karyawan@hcms.test')->first();
        $this->actingAs($employeeUser)->postJson('/leave-requests', [
            'leave_type_id' => LeaveType::where('name', 'Cuti Tahunan')->value('id'),
            'start_date' => '2026-11-02', 'end_date' => '2026-11-04', 'reason' => 'Liburan',
        ])->assertOk();
        $leave = LeaveRequest::where('employee_id', $employeeUser->employee->id)->latest('id')->first();
        $this->assertSame(3, $leave->total_days);

        $this->actingAs(User::where('email', 'manager@hcms.test')->first())->postJson("/leave-requests/{$leave->id}/approve")->assertOk();
        $this->assertSame('approved_manager', $leave->fresh()->status);

        $this->actingAs(User::where('email', 'hr@hcms.test')->first())->postJson("/leave-requests/{$leave->id}/approve")->assertOk();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(3, Attendance::where('employee_id', $leave->employee_id)->where('status', 'cuti')
            ->whereBetween('attendance_date', ['2026-11-02', '2026-11-04'])->count());
    }
}
