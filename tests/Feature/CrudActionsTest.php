<?php

namespace Tests\Feature;

use App\Models\AdhocComponent;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menjalankan seluruh aksi create / update / delete tiap modul dan memastikan tidak ada error 500.
 */
class CrudActionsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'superadmin@hcms.test')->firstOrFail());
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function masterTypes(): array
    {
        return [
            'departments' => ['departments', ['code' => 'TST', 'name' => 'Departemen Uji'], ['code' => 'TST2', 'name' => 'Departemen Uji 2']],
            'positions' => ['positions', ['code' => 'POS-T', 'name' => 'Jabatan Uji', 'level' => 'L5'], ['code' => 'POS-T2', 'name' => 'Jabatan Uji 2', 'level' => 'L4']],
            'work-locations' => ['work-locations', ['name' => 'Lokasi Uji', 'address' => 'Jl. Uji 1'], ['name' => 'Lokasi Uji 2', 'address' => null]],
            'shifts' => ['shifts', ['name' => 'Shift Uji', 'start_time' => '07:00', 'end_time' => '16:00', 'late_tolerance_min' => 10], ['name' => 'Shift Uji 2', 'start_time' => '09:00', 'end_time' => '18:00', 'late_tolerance_min' => 0]],
            'leave-types' => ['leave-types', ['name' => 'Cuti Uji', 'default_quota' => 5, 'is_paid' => true], ['name' => 'Cuti Uji 2', 'default_quota' => 3, 'is_paid' => false]],
            'holidays' => ['holidays', ['holiday_date' => '2027-03-03', 'name' => 'Libur Uji'], ['holiday_date' => '2027-03-04', 'name' => 'Libur Uji 2']],
        ];
    }

    /**
     * @param  array<string, mixed>  $create
     * @param  array<string, mixed>  $update
     */
    #[DataProvider('masterTypes')]
    public function test_master_data_crud(string $type, array $create, array $update): void
    {
        $this->get("/master/{$type}")->assertOk();
        $this->postJson("/master/{$type}", [])->assertStatus(422);
        $this->postJson("/master/{$type}", $create)->assertOk();

        $id = collect($this->getJson("/master/{$type}?per_page=100")->assertOk()->json('data'))->firstWhere('name', $create['name'])['id'] ?? null;
        $this->assertNotNull($id, 'Data baru tidak muncul di list');

        $this->putJson("/master/{$type}/{$id}", $update)->assertOk();
        $this->getJson("/master/{$type}?q=".urlencode($update['name']))->assertOk()->assertJsonPath('data.0.name', $update['name']);

        $this->deleteJson("/master/{$type}/{$id}")->assertOk();
        $this->putJson("/master/{$type}/{$id}", $update)->assertNotFound();
        $this->getJson('/master/unknown')->assertNotFound();
    }

    public function test_master_unique_code_is_validated_not_500(): void
    {
        $existing = Department::first();
        $this->postJson('/master/departments', ['code' => $existing->code, 'name' => 'Dobel'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->putJson("/master/departments/{$existing->id}", ['code' => $existing->code, 'name' => 'Nama Baru'])->assertOk();
    }

    public function test_duplicate_holiday_date_is_validated_not_500(): void
    {
        $holiday = Holiday::first() ?? Holiday::create(['company_id' => Company::value('id'), 'holiday_date' => '2026-12-25', 'name' => 'Natal']);

        $this->postJson('/master/holidays', ['holiday_date' => $holiday->holiday_date->toDateString(), 'name' => 'Duplikat'])
            ->assertStatus(422)->assertJsonValidationErrors(['holiday_date' => 'sudah terdaftar']);

        // update dengan tanggal sendiri boleh, tanggal hari libur lain ditolak
        $this->putJson("/master/holidays/{$holiday->id}", ['holiday_date' => $holiday->holiday_date->toDateString(), 'name' => 'Ganti Nama'])->assertOk();
        $other = Holiday::create(['company_id' => $holiday->company_id, 'holiday_date' => '2027-05-05', 'name' => 'Lain']);
        $this->putJson("/master/holidays/{$other->id}", ['holiday_date' => $holiday->holiday_date->toDateString(), 'name' => 'Lain'])->assertStatus(422);
    }

    public function test_deleting_shift_in_use_is_rejected(): void
    {
        $shift = Shift::whereHas('attendances')->firstOrFail();

        $this->deleteJson("/master/shifts/{$shift->id}")->assertStatus(422);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_deleting_master_in_use_does_not_silently_remove_related_data(): void
    {
        $department = Department::has('employees')->firstOrFail();
        $employees = $department->employees()->count();
        $response = $this->deleteJson("/master/departments/{$department->id}");
        $this->assertSame(422, $response->status(), "Departemen dipakai {$employees} karyawan tetap terhapus (department_id karyawan jadi NULL).");
        $this->assertSame($employees, $department->employees()->count());

        // karyawan yang sudah dihapus (soft delete) tetap menahan departemennya
        $solo = Department::create(['company_id' => Company::value('id'), 'code' => 'SOLO', 'name' => 'Solo']);
        Employee::first()->replicate(['employee_no'])->fill(['employee_no' => 'EMP-X-001', 'department_id' => $solo->id, 'user_id' => null])->save();
        Employee::where('employee_no', 'EMP-X-001')->first()->delete();
        $this->deleteJson("/master/departments/{$solo->id}")->assertStatus(422);
    }

    public function test_deleting_leave_type_in_use_does_not_cascade_leave_requests(): void
    {
        $type = LeaveType::has('requests')->first();
        if (! $type) {
            $type = LeaveType::first();
            LeaveRequest::create(['employee_id' => Employee::first()->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01',
                'end_date' => '2026-12-01', 'total_days' => 1, 'reason' => 'x', 'status' => 'approved']);
        }
        $before = LeaveRequest::count();

        $response = $this->deleteJson("/master/leave-types/{$type->id}");
        $this->assertSame(422, $response->status(), 'Jenis cuti yang dipakai terhapus dan '.($before - LeaveRequest::count()).' pengajuan cuti ikut terhapus (cascade).');
        $this->assertSame($before, LeaveRequest::count());
    }

    public function test_employee_crud(): void
    {
        $payload = [
            'full_name' => 'Karyawan Uji', 'nik_ktp' => '3271000000000099', 'gender' => 'P', 'ptkp_status' => 'K/1',
            'email' => 'karyawan.uji@nexa.co.id', 'phone' => '081299990000',
            'department_id' => Department::first()->id, 'position_id' => Position::first()->id, 'work_location_id' => WorkLocation::first()?->id,
            'employment_status' => 'tetap', 'join_date' => '2026-09-01', 'bank_name' => 'BCA', 'account_no' => '9876543210',
        ];
        $id = $this->postJson('/employees', $payload)->assertOk()->json('id');

        $this->getJson("/employees/{$id}")->assertOk()->assertJsonPath('email', 'karyawan.uji@nexa.co.id');
        $this->get("/employees/{$id}")->assertOk();

        // update dengan email yang sama (harus di-ignore) + ubah ke kontrak
        $this->putJson("/employees/{$id}", array_merge($payload, [
            'full_name' => 'Karyawan Uji Edit', 'employment_status' => 'kontrak', 'contract_end_date' => '2027-08-31', 'account_no' => '1111111111',
        ]))->assertOk();
        $employee = Employee::findOrFail($id);
        $this->assertSame('Karyawan Uji Edit', $employee->full_name);
        $this->assertSame('1111111111', $employee->primaryBank->account_no);
        $this->assertSame(1, $employee->bankAccounts()->count());

        // update kedua kali tidak boleh menggandakan kontrak
        $this->putJson("/employees/{$id}", array_merge($payload, ['employment_status' => 'kontrak', 'contract_end_date' => '2027-12-31']))->assertOk();
        $this->assertSame(1, $employee->contracts()->count());

        // email milik karyawan lain ditolak
        $this->putJson("/employees/{$id}", array_merge($payload, ['email' => Employee::whereKeyNot($id)->value('email')]))->assertStatus(422);
        // manager tidak boleh diri sendiri
        $this->putJson("/employees/{$id}", array_merge($payload, ['manager_id' => $id]))->assertStatus(422);

        $this->postJson("/employees/{$id}/resign", [])->assertStatus(422);
        $this->postJson("/employees/{$id}/resign", ['resign_date' => '2026-10-31', 'reason' => 'Uji'])->assertOk();
        $this->assertFalse($employee->fresh()->is_active);

        $this->deleteJson("/employees/{$id}")->assertOk();
        $this->assertSoftDeleted('employees', ['id' => $id]);
        $this->getJson("/employees/{$id}")->assertNotFound();

        // email karyawan terhapus boleh dipakai lagi; nomor karyawan tidak bentrok
        $this->postJson('/employees', $payload)->assertOk();
    }

    public function test_employee_import_and_export(): void
    {
        $this->get('/employees/export')->assertOk()->assertDownload();
        $this->postJson('/employees/import', [])->assertStatus(422);
    }

    public function test_attendance_create_and_update(): void
    {
        $employee = Employee::where('is_active', true)->first();
        $shift = Shift::where('name', 'Shift Pagi')->first();
        $date = '2026-01-05';
        Attendance::where('employee_id', $employee->id)->whereDate('attendance_date', $date)->delete();

        $this->postJson('/attendance', [
            'employee_id' => $employee->id, 'attendance_date' => $date, 'shift_id' => $shift->id,
            'check_in' => '08:20', 'check_out' => '17:10', 'status' => 'hadir',
        ])->assertOk();
        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('attendance_date', $date)->firstOrFail();
        $this->assertSame('terlambat', $attendance->status);

        $this->postJson('/attendance', ['employee_id' => $employee->id, 'attendance_date' => now()->addDays(3)->toDateString(),
            'shift_id' => $shift->id, 'status' => 'hadir'])->assertStatus(422)->assertJsonValidationErrors('attendance_date');
        $this->postJson('/attendance', ['employee_id' => $employee->id, 'attendance_date' => '2026-01-06', 'shift_id' => $shift->id,
            'check_in' => '17:00', 'check_out' => '08:00', 'status' => 'hadir'])->assertStatus(422)->assertJsonValidationErrors('check_out');
        $this->postJson('/attendance', ['employee_id' => $employee->id, 'attendance_date' => '2026-01-07', 'shift_id' => $shift->id,
            'status' => 'sakit'])->assertOk();

        $this->putJson("/attendance/{$attendance->id}", ['shift_id' => $shift->id, 'check_in' => '07:55', 'check_out' => '17:00', 'status' => 'hadir', 'notes' => 'Koreksi'])->assertOk();
        $this->assertSame(0, $attendance->fresh()->late_minutes);

        $this->getJson('/attendance?date='.$date)->assertOk();
        $this->get('/attendance/export?month=2026-01')->assertOk()->assertDownload();
        $this->postJson('/attendance/import', [])->assertStatus(422);
    }

    public function test_leave_request_create_approve_reject(): void
    {
        $employee = Employee::where('is_active', true)->whereKeyNot(auth()->user()->employee?->id)->first();
        $type = LeaveType::first();

        $this->postJson('/leave-requests', ['leave_type_id' => $type->id, 'start_date' => '2026-12-07', 'end_date' => '2026-12-05', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
        $this->postJson('/leave-requests', ['employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-12-07', 'end_date' => '2026-12-11', 'reason' => 'Uji'])->assertOk();
        $this->postJson('/leave-requests', ['employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-12-09', 'end_date' => '2026-12-09', 'reason' => 'Overlap'])->assertStatus(422);

        $leave = LeaveRequest::where('employee_id', $employee->id)->latest('id')->first();
        $this->assertSame(5, $leave->total_days);
        $this->postJson("/leave-requests/{$leave->id}/reject")->assertOk();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->postJson("/leave-requests/{$leave->id}/approve")->assertForbidden();

        $this->postJson('/leave-requests', ['employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-12-09', 'end_date' => '2026-12-09', 'reason' => 'Setelah ditolak boleh lagi'])->assertOk();
        $this->getJson('/leave-requests?status=pending')->assertOk();
    }

    public function test_overtime_request_create_approve_reject(): void
    {
        $employee = Employee::where('is_active', true)->whereKeyNot(auth()->user()->employee?->id)->first();

        $this->postJson('/overtime-requests', ['employee_id' => $employee->id, 'overtime_date' => '2026-10-05', 'hours' => 20, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('hours');
        $this->postJson('/overtime-requests', ['employee_id' => $employee->id, 'overtime_date' => '2026-10-05', 'hours' => 2.5, 'reason' => 'Uji'])->assertOk();
        $this->postJson('/overtime-requests', ['employee_id' => $employee->id, 'overtime_date' => '2026-10-06', 'hours' => 1, 'reason' => 'Uji 2'])->assertOk();

        [$a, $b] = OvertimeRequest::where('employee_id', $employee->id)->latest('id')->take(2)->get()->all();
        $this->postJson("/overtime-requests/{$a->id}/approve")->assertOk();
        $this->postJson("/overtime-requests/{$b->id}/reject")->assertOk();
        $this->postJson("/overtime-requests/{$a->id}/reject")->assertForbidden();
        $this->getJson('/overtime-requests')->assertOk();
    }

    public function test_salary_create_and_history(): void
    {
        $employee = Employee::where('is_active', true)->first();

        $this->postJson('/salaries', [])->assertStatus(422);
        $this->postJson('/salaries', ['employee_id' => $employee->id, 'basic_salary' => 12_500_000, 'salary_type' => 'monthly',
            'effective_date' => '2027-01-01', 'reason' => 'Kenaikan tahunan'])->assertOk();
        $this->postJson('/salaries', ['employee_id' => $employee->id, 'basic_salary' => 13_000_000, 'salary_type' => 'monthly',
            'effective_date' => '2026-12-01', 'reason' => 'Mundur'])->assertStatus(422)->assertJsonValidationErrors('effective_date');

        $this->getJson("/salaries/{$employee->id}/history")->assertOk()->assertJsonPath('rows.0.basic_salary', 12500000);
        $this->get('/salaries/export')->assertOk()->assertDownload();
    }

    public function test_pay_component_crud(): void
    {
        $payload = ['code' => 'ALW-UJI', 'name' => 'Tunjangan Uji', 'type' => 'allowance', 'nature' => 'fixed',
            'calc_method' => 'nominal', 'default_value' => 250000, 'is_taxable' => true, 'is_active' => true];

        $this->postJson('/components', array_merge($payload, ['code' => 'alw uji']))->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/components', $payload)->assertOk();
        $this->postJson('/components', $payload)->assertStatus(422)->assertJsonValidationErrors('code');
        $component = PayComponent::where('code', 'ALW-UJI')->firstOrFail();

        $this->putJson("/components/{$component->id}", array_merge($payload, ['name' => 'Tunjangan Uji Edit', 'calc_method' => 'percent', 'default_value' => 5]))->assertOk();
        $this->assertSame('Tunjangan Uji Edit', $component->fresh()->name);

        // komponen yang belum dipakai -> benar-benar dihapus
        $this->deleteJson("/components/{$component->id}")->assertOk();
        $this->assertDatabaseMissing('pay_components', ['id' => $component->id]);

        // komponen yang dipakai -> hanya dinonaktifkan
        $used = EmployeeComponent::first()->payComponent;
        $this->deleteJson("/components/{$used->id}")->assertOk();
        $this->assertFalse($used->fresh()->is_active);
    }

    public function test_employee_component_crud(): void
    {
        $employee = Employee::where('is_active', true)->first();
        $component = PayComponent::create(['code' => 'ALW-EC', 'name' => 'Tunj EC', 'type' => 'allowance', 'nature' => 'fixed',
            'calc_method' => 'nominal', 'default_value' => 0, 'is_taxable' => true, 'is_active' => true]);
        $payload = ['employee_id' => $employee->id, 'pay_component_id' => $component->id, 'value' => 300000, 'start_date' => '2026-10-01'];

        $this->postJson('/components/employee', $payload)->assertOk();
        $this->postJson('/components/employee', $payload)->assertStatus(422)->assertJsonValidationErrors('pay_component_id');
        $assignment = EmployeeComponent::where('pay_component_id', $component->id)->firstOrFail();

        $this->putJson("/components/employee/{$assignment->id}", array_merge($payload, ['value' => 400000, 'end_date' => '2026-09-01']))
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
        $this->putJson("/components/employee/{$assignment->id}", array_merge($payload, ['value' => 400000, 'end_date' => '2026-12-31']))->assertOk();
        $this->assertEquals(400000, $assignment->fresh()->value);

        $this->getJson('/components/employee?active=0')->assertOk();
        $this->deleteJson("/components/employee/{$assignment->id}")->assertOk();
        $this->assertDatabaseMissing('employee_components', ['id' => $assignment->id]);
    }

    public function test_adhoc_component_create_and_delete(): void
    {
        $open = PayrollPeriod::whereNot('status', 'final')->firstOrFail();
        $final = PayrollPeriod::where('status', 'final')->firstOrFail();
        $payload = ['employee_id' => Employee::where('is_active', true)->value('id'), 'pay_component_id' => PayComponent::value('id'),
            'payroll_period_id' => $open->id, 'amount' => 500000, 'notes' => 'Uji'];

        $this->postJson('/components/adhoc', array_merge($payload, ['payroll_period_id' => $final->id]))->assertStatus(422);
        $this->postJson('/components/adhoc', $payload)->assertOk();
        $adhoc = AdhocComponent::latest('id')->first();

        $this->getJson('/components/adhoc')->assertOk();
        $this->deleteJson("/components/adhoc/{$adhoc->id}")->assertOk();
        $this->assertDatabaseMissing('adhoc_components', ['id' => $adhoc->id]);

        $locked = AdhocComponent::create(array_merge($payload, ['payroll_period_id' => $final->id]));
        $this->deleteJson("/components/adhoc/{$locked->id}")->assertStatus(422);
    }

    public function test_loan_create(): void
    {
        $this->postJson('/components/loans', ['employee_id' => Employee::value('id'), 'total_amount' => 50000, 'tenor_months' => 3, 'start_date' => '2026-11-01'])
            ->assertStatus(422);
        $this->postJson('/components/loans', ['employee_id' => Employee::value('id'), 'total_amount' => 1_000_000, 'tenor_months' => 3, 'start_date' => '2026-11-01'])
            ->assertOk();

        $loan = Loan::latest('id')->first();
        $this->assertEquals(333334, $loan->installment_amount);
        $this->getJson('/components/loans')->assertOk();
    }

    public function test_payroll_period_create_and_payslip_send(): void
    {
        Queue::fake();
        $this->postJson('/payroll/periods', ['month' => 1, 'year' => 2027])->assertOk();
        $this->postJson('/payroll/periods', ['month' => 1, 'year' => 2027])->assertStatus(422)->assertJsonValidationErrors('year');
        $this->assertTrue(PayrollPeriod::where('month', 1)->where('year', 2027)->exists());

        $payslip = Payslip::firstOrFail();
        $this->postJson("/payslips/{$payslip->id}/send")->assertOk();
        $this->postJson('/payslips/send-bulk', ['period' => $payslip->register->payroll_period_id])->assertOk();
    }

    public function test_user_crud(): void
    {
        $role = Role::where('name', 'HR Admin')->firstOrFail();
        $employee = Employee::whereNull('user_id')->where('is_active', true)->firstOrFail();

        $this->postJson('/settings/users', ['name' => 'User Uji', 'email' => 'user.uji@hcms.test', 'role_id' => $role->id, 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/settings/users', ['name' => 'User Uji', 'email' => 'user.uji@hcms.test', 'role_id' => $role->id,
            'employee_id' => $employee->id, 'password' => 'Rahasia123', 'is_active' => true])->assertOk();
        $user = User::where('email', 'user.uji@hcms.test')->firstOrFail();
        $this->assertSame($user->id, $employee->fresh()->user_id);

        // update tanpa password, lepas link karyawan
        $this->putJson("/settings/users/{$user->id}", ['name' => 'User Uji Edit', 'email' => 'user.uji@hcms.test', 'role_id' => $role->id, 'is_active' => false])->assertOk();
        $this->assertSame('User Uji Edit', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertNull($employee->fresh()->user_id);

        // login sendiri tidak bisa dinonaktifkan / dihapus
        $me = auth()->user();
        $this->putJson("/settings/users/{$me->id}", ['name' => $me->name, 'email' => $me->email, 'role_id' => $me->role_id, 'is_active' => false])->assertStatus(422);
        $this->putJson("/settings/users/{$me->id}", ['name' => 'Super Edit', 'email' => $me->email, 'role_id' => $me->role_id])->assertOk();
        $this->deleteJson("/settings/users/{$me->id}")->assertStatus(422);

        $this->deleteJson("/settings/users/{$user->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_role_crud(): void
    {
        $permissions = Permission::limit(3)->pluck('id')->all();

        $this->postJson('/settings/roles', ['name' => 'Role Uji', 'data_scope' => 'invalid'])->assertStatus(422);
        $this->postJson('/settings/roles', ['name' => 'Role Uji', 'description' => 'desc', 'data_scope' => 'department', 'permissions' => $permissions])->assertOk();
        $role = Role::where('name', 'Role Uji')->firstOrFail();
        $this->assertSame(3, $role->permissions()->count());

        $this->putJson("/settings/roles/{$role->id}", ['name' => 'Role Uji Edit', 'data_scope' => 'self', 'permissions' => [$permissions[0]]])->assertOk();
        $this->assertSame(1, $role->permissions()->count());
        $this->assertSame('Role Uji Edit', $role->fresh()->name);

        $system = Role::where('is_system', true)->firstOrFail();
        $this->deleteJson("/settings/roles/{$system->id}")->assertStatus(422);
        $this->putJson("/settings/roles/{$system->id}", ['name' => 'Ganti Nama', 'data_scope' => $system->data_scope,
            'permissions' => $system->permissions()->pluck('permissions.id')->all()])->assertOk();
        $this->assertNotSame('Ganti Nama', $system->fresh()->name);

        $used = Role::has('users')->where('is_system', false)->first();
        if ($used) {
            $this->deleteJson("/settings/roles/{$used->id}")->assertStatus(422);
        }

        $this->deleteJson("/settings/roles/{$role->id}")->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_company_settings_update(): void
    {
        $this->putJson('/settings/company', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson('/settings/company', ['name' => 'PT Uji', 'npwp' => '012345678901234', 'phone' => '021-555', 'address' => 'Jakarta'])->assertOk();
        $this->assertSame('PT Uji', Company::first()->name);
    }
}
