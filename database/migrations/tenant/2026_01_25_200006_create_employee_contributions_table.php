<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the employee_contributions table for storing per-employee contribution records.
     * Each record represents a single government contribution for a payroll period.
     */
    public function up(): void
    {
        Schema::create('employee_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->date('payroll_period_start');
            $table->date('payroll_period_end');
            $table->string('contribution_type');
            $table->decimal('basis_salary', 12, 2);
            $table->decimal('employee_share', 10, 2);
            $table->decimal('employer_share', 10, 2);
            $table->decimal('total_contribution', 10, 2);
            $table->decimal('sss_ec_contribution', 10, 2)->nullable();
            $table->string('contribution_table_type');
            $table->unsignedBigInteger('contribution_table_id');
            $table->index(['contribution_table_type', 'contribution_table_id'], 'emp_contrib_table_idx');
            $table->text('remarks')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_id', 'payroll_period_start', 'payroll_period_end', 'contribution_type'],
                'unique_employee_contribution_period'
            );
            $table->index('employee_id');
            $table->index('contribution_type');
            $table->index(['payroll_period_start', 'payroll_period_end'], 'emp_contrib_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_contributions');
    }
};
