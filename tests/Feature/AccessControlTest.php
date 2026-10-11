<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Halaman => status yang diharapkan per role (superadmin, hr, payroll, manager, karyawan).
     *
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function pages(): array
    {
        $all = fn (int $s) => ['superadmin' => $s, 'hr' => $s, 'payroll' => $s, 'manager' => $s, 'karyawan' => $s];

        return [
            'dashboard' => ['/', $all(200)],
            'karyawan' => ['/employees', $all(200)],
            'master departemen' => ['/master/departments', ['superadmin' => 200, 'hr' => 200, 'payroll' => 403, 'manager' => 403, 'karyawan' => 403]],
            'absensi' => ['/attendance', $all(200)],
            'data gaji' => ['/salaries', ['superadmin' => 200, 'hr' => 200, 'payroll' => 200, 'manager' => 403, 'karyawan' => 403]],
            'komponen' => ['/components', ['superadmin' => 200, 'hr' => 200, 'payroll' => 200, 'manager' => 403, 'karyawan' => 403]],
            'register gaji' => ['/payroll', ['superadmin' => 200, 'hr' => 200, 'payroll' => 200, 'manager' => 403, 'karyawan' => 403]],
            'payslip' => ['/payslips', ['superadmin' => 200, 'hr' => 200, 'payroll' => 200, 'manager' => 403, 'karyawan' => 403]],
            'payslip saya' => ['/my-payslips', ['superadmin' => 200, 'hr' => 403, 'payroll' => 403, 'manager' => 403, 'karyawan' => 200]],
            'report' => ['/reports', ['superadmin' => 200, 'hr' => 200, 'payroll' => 200, 'manager' => 200, 'karyawan' => 403]],
            'users' => ['/settings/users', ['superadmin' => 200, 'hr' => 403, 'payroll' => 403, 'manager' => 403, 'karyawan' => 403]],
            'roles' => ['/settings/roles', ['superadmin' => 200, 'hr' => 403, 'payroll' => 403, 'manager' => 403, 'karyawan' => 403]],
            'perusahaan' => ['/settings/company', ['superadmin' => 200, 'hr' => 403, 'payroll' => 403, 'manager' => 403, 'karyawan' => 403]],
            'audit log' => ['/settings/audit-logs', ['superadmin' => 200, 'hr' => 403, 'payroll' => 403, 'manager' => 403, 'karyawan' => 403]],
        ];
    }

    /**
     * @param  array<string, int>  $expected
     */
    #[DataProvider('pages')]
    public function test_menu_access_follows_rbac_matrix(string $url, array $expected): void
    {
        foreach ($expected as $account => $status) {
            $this->actingAs($this->user($account))->get($url)->assertStatus($status);
        }
    }

    public function test_json_endpoints_respond(): void
    {
        $this->actingAs($this->user('superadmin'));
        $period = PayrollPeriod::where('status', 'final')->first();

        foreach (['/employees', '/attendance', '/leave-requests', '/overtime-requests', '/salaries', '/salaries?view=history',
            '/components', '/components/employee', '/components/adhoc', '/components/loans', "/payroll/{$period->id}/registers",
            "/payslips?period={$period->id}", '/master/shifts', '/master/holidays', '/settings/users', '/settings/audit-logs'] as $url) {
            $this->getJson($url)->assertOk()->assertJsonStructure(['data', 'current_page', 'total']);
        }
    }

    public function test_reports_preview_and_export(): void
    {
        $this->actingAs($this->user('superadmin'));

        foreach (\App\Models\ReportDefinition::pluck('code') as $code) {
            $this->getJson("/reports/{$code}/preview")->assertOk()->assertJsonStructure(['columns', 'rows', 'total']);
        }
        $this->get('/reports/PAY-REGISTER/export?format=xlsx')->assertOk()->assertDownload();
        $this->get('/reports/EMP-LIST/export?format=pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_hr_cannot_open_salary_reports(): void
    {
        $this->actingAs($this->user('hr'))->getJson('/reports/PAY-REGISTER/preview')->assertForbidden();
        $this->actingAs($this->user('payroll'))->getJson('/reports/PAY-REGISTER/preview')->assertOk();
    }

    public function test_manager_only_sees_subordinates(): void
    {
        $manager = $this->user('manager');
        $expected = $manager->employee->subordinateIds(true);

        $ids = collect($this->actingAs($manager)->getJson('/employees?per_page=100&active=')->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing($expected, $ids->all());
        $outsider = Employee::whereNotIn('id', $expected)->first();
        $this->get("/employees/{$outsider->id}")->assertForbidden();
    }

    public function test_employee_only_sees_own_payslip(): void
    {
        $karyawan = $this->user('karyawan');
        $own = Payslip::whereHas('register', fn ($q) => $q->where('employee_id', $karyawan->employee->id))->first();
        $other = Payslip::whereHas('register', fn ($q) => $q->where('employee_id', '!=', $karyawan->employee->id))->first();

        $this->actingAs($karyawan)->get("/payslips/{$own->id}/preview")->assertOk()->assertSee($own->payslip_no);
        $this->get("/payslips/{$own->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/payslips/{$other->id}/preview")->assertForbidden();
        $this->assertNotNull($own->fresh()->viewed_at);
    }

    public function test_denied_access_is_audited(): void
    {
        $this->actingAs($this->user('karyawan'))->get('/settings/users')->assertForbidden();

        $this->assertTrue(AuditLog::where('action', 'access.denied')->where('user_id', $this->user('karyawan')->id)->exists());
    }

    public function test_sidebar_hides_menu_without_permission(): void
    {
        $this->actingAs($this->user('karyawan'))->get('/')
            ->assertSee('Payslip Saya')
            ->assertDontSee('Register Gaji')
            ->assertDontSee('Role &amp; Permission', false);
    }

    protected function user(string $account): User
    {
        return User::where('email', "{$account}@hcms.test")->firstOrFail();
    }
}
