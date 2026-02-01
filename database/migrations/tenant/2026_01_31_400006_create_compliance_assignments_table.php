<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_assignments table for employee training assignments.
     * Tracks assignment status, due dates, and completion information.
     */
    public function up(): void
    {
        Schema::create('compliance_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_course_id')->constrained('compliance_courses')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assignment_rule_id')->nullable()->constrained('compliance_assignment_rules')->nullOnDelete();
            $table->string('status', 50)->default('pending');
            $table->date('assigned_date');
            $table->date('due_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->integer('attempts_used')->default(0);
            $table->integer('total_time_minutes')->default(0);
            $table->date('valid_until')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->foreignId('exempted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('exempted_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('acknowledgment_completed')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('compliance_course_id');
            $table->index('employee_id');
            $table->index('assignment_rule_id');
            $table->index('status');
            $table->index('assigned_date');
            $table->index('due_date');
            $table->index('valid_until');
            $table->index(['employee_id', 'status']);
            $table->index(['compliance_course_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_assignments');
    }
};
