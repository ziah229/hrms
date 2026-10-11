<?php

namespace Tests\Feature;

use App\Models\AdhocComponent;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_full_lifecycle_process_approve_finalize_reopen(): void
    {
        $period = PayrollPeriod::where('name', 'Oktober 2026')->firstOrFail();
        $payroll = User::where('email', 'payroll@hcms.test')->first();
        $super = User::where('email', 'superadmin@hcms.test')->first();

        // Payroll Officer: proses boleh, approve tidak (matriks VPF).
        $this->actingAs($payroll)->postJson("/payroll/{$period->id}/process")->assertOk();
        $this->assertSame('processed', $period->fresh()->status);
        $this->postJson("/payroll/{$period->id}/approve")->assertForbidden();

        $this->actingAs($super)->postJson("/payroll/{$period->id}/approve")->assertOk();
        $this->actingAs($payroll)->postJson("/payroll/{$period->id}/finalize")->assertOk();

        $period->refresh();
        $this->assertSame('final', $period->status);
        $this->assertSame($period->registers()->count(), \App\Models\Payslip::whereIn('payroll_register_id', $period->registers()->pluck('id'))->count());

        // Periode final terkunci.
        $this->actingAs($payroll)->postJson("/payroll/{$period->id}/process")->assertStatus(422);

        // Reopen hanya Super Admin dan wajib alasan.
        $this->postJson("/payroll/{$period->id}/reopen", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($super)->postJson("/payroll/{$period->id}/reopen")->assertStatus(422);
        $this->postJson("/payroll/{$period->id}/reopen", ['reason' => 'Koreksi lembur'])->assertOk();
        $this->assertSame('processed', $period->fresh()->status);
    }

    public function test_register_net_salary_matches_formula_and_includes_adhoc(): void
    {
        $period = PayrollPeriod::where('name', 'Oktober 2026')->firstOrFail();
        $employee = Employee::where('is_active', true)->first();
        AdhocComponent::create([
            'employee_id' => $employee->id,
            'pay_component_id' => PayComponent::where('code', 'ALW-BON')->value('id'),
            'payroll_period_id' => $period->id,
            'amount' => 1_000_000,
            'notes' => 'Bonus uji',
        ]);

        $this->actingAs(User::where('email', 'payroll@hcms.test')->first())->postJson("/payroll/{$period->id}/process")->assertOk();

        $register = PayrollRegister::with('items')->where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('review', $register->status);
        $this->assertTrue($register->items->contains(fn ($i) => str_contains($i->description, 'Bonus uji')));
        $this->assertEqualsWithDelta(
            $register->basic_salary + $register->total_allowance - $register->total_deduction - $register->tax_amount,
            (float) $register->net_salary,
            1
        );
        $this->assertSame('Agustus 2026', PayrollRegister::where('employee_id', $employee->id)->orderBy('id')->first()->period->name);
    }

    public function test_salary_change_creates_new_row_and_closes_previous(): void
    {
        $employee = Employee::first();
        $before = EmployeeSalary::where('employee_id', $employee->id)->count();

        $this->actingAs(User::where('email', 'payroll@hcms.test')->first())->postJson('/salaries', [
            'employee_id' => $employee->id, 'basic_salary' => 99_000_000, 'salary_type' => 'monthly',
            'effective_date' => '2026-11-01', 'reason' => 'Promosi jabatan',
        ])->assertOk();

        $this->assertSame($before + 1, EmployeeSalary::where('employee_id', $employee->id)->count());
        $this->assertSame('2026-10-31', EmployeeSalary::where('employee_id', $employee->id)->where('effective_date', '<', '2026-11-01')->latest('effective_date')->first()->end_date->toDateString());
        $this->assertEquals(99_000_000, $employee->salaryAt('2026-11-15')->basic_salary);
    }
}
