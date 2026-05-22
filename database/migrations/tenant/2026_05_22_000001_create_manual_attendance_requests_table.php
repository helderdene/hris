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
        Schema::create('manual_attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->text('reason');
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('decided_by_role')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status']);
            $table->index(['attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_attendance_requests');
    }
};
