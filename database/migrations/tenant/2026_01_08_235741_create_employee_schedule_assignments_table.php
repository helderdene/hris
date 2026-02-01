<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the employee_schedule_assignments table for assigning employees to work schedules.
     * Supports effective dating with one active schedule per employee at a time.
     */
    public function up(): void
    {
        Schema::create('employee_schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->foreignId('work_schedule_id')
                ->constrained('work_schedules')
                ->onDelete('cascade');
            $table->string('shift_name')->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index('effective_date');
            $table->index('end_date');
            $table->index('employee_id');
            $table->unique(['employee_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_schedule_assignments');
    }
};
