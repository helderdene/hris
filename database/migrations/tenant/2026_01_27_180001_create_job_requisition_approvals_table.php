<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the job_requisition_approvals table for tracking approval chain.
     * Each approval level has its own record with approver details and decision.
     */
    public function up(): void
    {
        Schema::create('job_requisition_approvals', function (Blueprint $table) {
            $table->id();

            // Relationship to job requisition
            $table->foreignId('job_requisition_id')->constrained()->onDelete('cascade');

            // Approval level in chain (1 = first level, 2 = second level, etc.)
            $table->smallInteger('approval_level');

            // Approver type for categorization
            $table->string('approver_type', 30)->default('supervisor');

            // Approver details (snapshot for audit trail)
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->string('approver_name')->nullable();
            $table->string('approver_position')->nullable();

            // Decision tracking
            $table->string('decision', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // Unique constraint: one approval per level per requisition
            $table->unique(['job_requisition_id', 'approval_level'], 'job_req_approvals_unique');

            // Indexes for common queries
            $table->index('decision');
            $table->index(['approver_employee_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_requisition_approvals');
    }
};
