<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the payroll_periods table for storing individual payroll period instances.
     * Each period represents an actual payroll run with specific date ranges and totals.
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_cycle_id')
                ->constrained('payroll_cycles')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('period_type');
            $table->integer('year');
            $table->integer('period_number');
            $table->date('cutoff_start');
            $table->date('cutoff_end');
            $table->date('pay_date');
            $table->string('status')->default('draft');
            $table->integer('employee_count')->default(0);
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            // closed_by references platform schema users table - no FK constraint
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint to prevent duplicate periods
            $table->unique(['payroll_cycle_id', 'year', 'period_number']);

            // Indexes for common queries
            $table->index('year');
            $table->index('status');
            $table->index('period_type');
            $table->index('pay_date');
            $table->index(['year', 'status']);
            $table->index(['cutoff_start', 'cutoff_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
