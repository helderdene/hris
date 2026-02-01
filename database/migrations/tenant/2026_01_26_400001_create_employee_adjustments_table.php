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
        Schema::create('employee_adjustments', function (Blueprint $table) {
            $table->id();

            // Employee relationship
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            // Adjustment classification
            $table->string('adjustment_category', 20);  // earning, deduction
            $table->string('adjustment_type', 50);       // allowance_transportation, bonus_performance, etc.
            $table->string('adjustment_code', 50);       // Unique code for this adjustment
            $table->string('name');                      // Display name
            $table->text('description')->nullable();

            // Amount configuration
            $table->decimal('amount', 12, 2);           // Amount per application
            $table->boolean('is_taxable')->default(true);

            // Frequency configuration
            $table->string('frequency', 20);            // one_time, recurring

            // Recurring-specific fields (nullable for one-time adjustments)
            $table->date('recurring_start_date')->nullable();
            $table->date('recurring_end_date')->nullable();
            $table->string('recurring_interval', 20)->nullable();  // every_period, monthly, quarterly
            $table->unsignedInteger('remaining_occurrences')->nullable();  // For limited-occurrence recurring

            // Balance tracking (for loan-type deductions)
            $table->boolean('has_balance_tracking')->default(false);
            $table->decimal('total_amount', 12, 2)->nullable();      // Original total for loans
            $table->decimal('total_applied', 12, 2)->default(0);     // Total applied so far
            $table->decimal('remaining_balance', 12, 2)->nullable(); // Remaining for loans

            // Target period (for one-time adjustments)
            $table->foreignId('target_payroll_period_id')
                ->nullable()
                ->constrained('payroll_periods')
                ->nullOnDelete();

            // Status and tracking
            $table->string('status', 20)->default('active');  // active, completed, on_hold, cancelled
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit (references users table in platform database - no foreign key)
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['employee_id', 'status']);
            $table->index(['adjustment_category', 'status']);
            $table->index(['frequency', 'status']);
            $table->index('target_payroll_period_id');

            // Unique constraint for adjustment code per employee
            $table->unique(['employee_id', 'adjustment_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_adjustments');
    }
};
