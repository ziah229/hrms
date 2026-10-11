<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2);
            $table->enum('salary_type', ['monthly', 'daily', 'hourly'])->default('monthly');
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('pay_components', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction']);
            $table->enum('nature', ['fixed', 'variable'])->default('fixed');
            $table->enum('calc_method', ['nominal', 'percent', 'per_day', 'formula'])->default('nominal');
            $table->decimal('default_value', 15, 2)->default(0);
            $table->string('formula')->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 15, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->enum('status', ['draft', 'processed', 'approved', 'final'])->default('draft');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['month', 'year']);
        });

        Schema::create('adhoc_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('start_date');
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['scheduled', 'paid'])->default('scheduled');
            $table->timestamps();

            $table->unique(['loan_id', 'payroll_period_id']);
        });

        Schema::create('payroll_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_allowance', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->enum('status', ['draft', 'verified', 'review', 'final'])->default('draft');
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('payroll_register_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_component_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['allowance', 'deduction']);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_register_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payslip_no')->unique();
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_register_items');
        Schema::dropIfExists('payroll_registers');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('adhoc_components');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_components');
        Schema::dropIfExists('pay_components');
        Schema::dropIfExists('employee_salaries');
    }
};
