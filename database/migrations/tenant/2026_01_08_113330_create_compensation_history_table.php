<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compensation_history table for tracking compensation changes over time.
     * Uses ended_at pattern: null for current record, datetime for historical records.
     */
    public function up(): void
    {
        Schema::create('compensation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');

            // Previous and new compensation values
            $table->decimal('previous_basic_pay', 12, 2)->nullable();
            $table->decimal('new_basic_pay', 12, 2);
            $table->string('previous_pay_type')->nullable();
            $table->string('new_pay_type');

            // Effective date and audit fields
            $table->date('effective_date');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('remarks')->nullable();

            // History tracking (null = current record)
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();

            // Composite index for efficient current record queries
            $table->index(['employee_id', 'ended_at'], 'comp_hist_current_idx');
            // Individual index on employee_id for relationship queries
            $table->index('employee_id', 'comp_hist_employee_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensation_history');
    }
};
