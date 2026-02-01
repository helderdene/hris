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
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Employee snapshot (captured at computation time for audit trail)
            $table->string('employee_number', 50);
            $table->string('employee_name');
            $table->string('department_name')->nullable();
            $table->string('position_name')->nullable();
            $table->decimal('basic_salary_snapshot', 12, 2);
            $table->string('pay_type_snapshot', 20);

            // DTR summary
            $table->decimal('days_worked', 5, 2)->default(0);
            $table->integer('total_regular_minutes')->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->integer('total_undertime_minutes')->default(0);
            $table->integer('total_overtime_minutes')->default(0);
            $table->integer('total_night_diff_minutes')->default(0);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('holiday_days', 5, 2)->default(0);

            // Earnings
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('night_diff_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('allowances_total', 12, 2)->default(0);
            $table->decimal('bonuses_total', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);

            // Government deductions (employee share)
            $table->decimal('sss_employee', 12, 2)->default(0);
            $table->decimal('sss_employer', 12, 2)->default(0);
            $table->decimal('philhealth_employee', 12, 2)->default(0);
            $table->decimal('philhealth_employer', 12, 2)->default(0);
            $table->decimal('pagibig_employee', 12, 2)->default(0);
            $table->decimal('pagibig_employer', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('other_deductions_total', 12, 2)->default(0);

            // Totals
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_employer_contributions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);

            // Status tracking
            $table->string('status', 20)->default('draft');
            $table->timestamp('computed_at')->nullable();
            // No foreign key - users table is on platform database
            $table->unsignedBigInteger('computed_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            // No foreign key - users table is on platform database
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Ensure one entry per employee per period
            $table->unique(['payroll_period_id', 'employee_id']);

            // Index for common queries
            $table->index(['payroll_period_id', 'status']);
            $table->index(['employee_id', 'payroll_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
