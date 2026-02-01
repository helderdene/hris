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
        Schema::create('daily_time_records', function (Blueprint $table) {
            $table->id();

            // Employee reference
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Date for this DTR
            $table->date('date');

            // Captured schedule at time of record (nullable for no schedule)
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();

            // For shifting schedules - capture which shift was worked
            $table->string('shift_name')->nullable();

            // Status enum: present, absent, holiday, rest_day, no_schedule
            $table->string('status')->default('present');

            // First and last punch times
            $table->datetime('first_in')->nullable();
            $table->datetime('last_out')->nullable();

            // Computed time values (stored in minutes)
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('total_break_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->boolean('overtime_approved')->default(false);
            $table->unsignedInteger('night_diff_minutes')->default(0);

            // Notes and review flags
            $table->text('remarks')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('review_reason')->nullable();

            // When calculations were performed
            $table->datetime('computed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['employee_id', 'date']);
            $table->index('date');
            $table->index('needs_review');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_records');
    }
};
