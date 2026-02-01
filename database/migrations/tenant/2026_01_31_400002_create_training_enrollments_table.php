<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the training_enrollments table for tracking employee enrollments in sessions.
     */
    public function up(): void
    {
        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status', 50)->default('confirmed');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('attended_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
            $table->index('status');
            $table->index('enrolled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
    }
};
