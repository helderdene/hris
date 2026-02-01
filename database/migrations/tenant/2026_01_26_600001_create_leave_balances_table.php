<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the leave_balances table for tracking employee leave credits.
     * Each employee has one balance record per leave type per year.
     */
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->integer('year');

            // Balance Components
            $table->decimal('brought_forward', 6, 2)->default(0);   // Carried from previous year
            $table->decimal('earned', 6, 2)->default(0);            // Credits accrued this year
            $table->decimal('used', 6, 2)->default(0);              // Days actually taken
            $table->decimal('pending', 6, 2)->default(0);           // Reserved for pending requests
            $table->decimal('adjustments', 6, 2)->default(0);       // Net manual adjustments
            $table->decimal('expired', 6, 2)->default(0);           // Forfeited carry-over

            // Carry-over Tracking
            $table->date('carry_over_expiry_date')->nullable();     // When brought_forward expires

            // Processing Timestamps
            $table->timestamp('last_accrual_at')->nullable();       // Last monthly accrual
            $table->timestamp('year_end_processed_at')->nullable(); // Year-end processing done

            $table->timestamps();

            // Unique constraint: one balance per employee per leave type per year
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_balances_unique');

            // Indexes for common queries
            $table->index('year');
            $table->index(['employee_id', 'year']);
            $table->index(['leave_type_id', 'year']);
            $table->index('carry_over_expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
