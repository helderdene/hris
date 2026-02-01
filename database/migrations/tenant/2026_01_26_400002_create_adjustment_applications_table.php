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
        Schema::create('adjustment_applications', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('employee_adjustment_id')
                ->constrained('employee_adjustments')
                ->cascadeOnDelete();

            $table->foreignId('payroll_period_id')
                ->constrained('payroll_periods')
                ->cascadeOnDelete();

            $table->foreignId('payroll_entry_id')
                ->nullable()
                ->constrained('payroll_entries')
                ->nullOnDelete();

            // Application details
            $table->decimal('amount', 12, 2);  // Amount applied this period

            // Balance tracking (for loan-type deductions)
            $table->decimal('balance_before', 12, 2)->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();

            // Timestamps
            $table->timestamp('applied_at')->nullable();
            $table->string('status', 20)->default('pending');  // pending, applied, reversed

            $table->timestamps();

            // Indexes
            $table->index('payroll_period_id');
            $table->index('payroll_entry_id');
            $table->index(['employee_adjustment_id', 'payroll_period_id'], 'adj_app_adjustment_period_idx');

            // Prevent duplicate applications
            $table->unique(['employee_adjustment_id', 'payroll_period_id'], 'unique_adjustment_application');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustment_applications');
    }
};
