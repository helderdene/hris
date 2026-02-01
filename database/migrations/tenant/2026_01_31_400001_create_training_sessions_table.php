<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the training_sessions table for scheduled training sessions.
     * Links to courses and tracks capacity, timing, and enrollment details.
     */
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title')->nullable()->comment('Optional override of course title');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('virtual_link')->nullable();
            $table->string('status', 50)->default('draft');
            $table->integer('max_participants')->nullable()->comment('Falls back to course.max_participants if null');
            $table->text('notes')->nullable();
            $table->foreignId('instructor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
            $table->index('course_id');
            $table->index('instructor_employee_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
