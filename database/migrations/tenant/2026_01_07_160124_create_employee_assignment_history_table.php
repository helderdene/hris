<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the employee_assignment_history table to track assignment changes over time.
     * Only one active assignment per type at a time (ended_at is null for current assignment).
     */
    public function up(): void
    {
        Schema::create('employee_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->string('assignment_type');
            $table->unsignedBigInteger('previous_value_id')->nullable();
            $table->unsignedBigInteger('new_value_id');
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            // Composite index for efficient current assignment queries
            $table->index(['employee_id', 'assignment_type', 'ended_at'], 'emp_assign_hist_current_idx');
            // Individual index on employee_id for relationship queries
            $table->index('employee_id', 'emp_assign_hist_employee_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_assignment_history');
    }
};
