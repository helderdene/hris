<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_courses table for compliance-specific course settings.
     * Stores due dates, validity periods, passing requirements, and notification settings.
     */
    public function up(): void
    {
        Schema::create('compliance_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->integer('days_to_complete')->default(30);
            $table->integer('validity_months')->nullable();
            $table->decimal('passing_score', 5, 2)->default(80.00);
            $table->integer('max_attempts')->default(3);
            $table->boolean('allow_retakes_after_pass')->default(false);
            $table->boolean('requires_acknowledgment')->default(false);
            $table->text('acknowledgment_text')->nullable();
            $table->json('reminder_days')->nullable();
            $table->json('escalation_days')->nullable();
            $table->boolean('auto_reassign_on_expiry')->default(true);
            $table->text('completion_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('course_id');
            $table->index('validity_months');
            $table->index('auto_reassign_on_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_courses');
    }
};
