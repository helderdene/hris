<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the probationary_evaluation_approvals table for tracking HR
     * approval chain. Follows the LeaveApplicationApproval pattern.
     */
    public function up(): void
    {
        Schema::create('probationary_evaluation_approvals', function (Blueprint $table) {
            $table->id();

            // Relationship to probationary evaluation
            $table->unsignedBigInteger('probationary_evaluation_id');
            $table->foreign('probationary_evaluation_id', 'prob_eval_approval_eval_fk')
                ->references('id')
                ->on('probationary_evaluations')
                ->onDelete('cascade');

            // Approval level in chain (1 = HR, 2 = HR Manager, etc.)
            $table->smallInteger('approval_level');

            // Approver type for categorization
            $table->string('approver_type', 30)->default('hr');

            // Approver details (snapshot for audit trail)
            $table->unsignedBigInteger('approver_employee_id')->nullable();
            $table->foreign('approver_employee_id', 'prob_eval_approval_emp_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('set null');
            $table->string('approver_name')->nullable();
            $table->string('approver_position')->nullable();

            // Decision tracking
            $table->string('decision', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // Unique constraint: one approval per level per evaluation
            $table->unique(['probationary_evaluation_id', 'approval_level'], 'prob_eval_approvals_unique');

            // Indexes for common queries
            $table->index('decision');
            $table->index(['approver_employee_id', 'decision'], 'prob_eval_approver_decision_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probationary_evaluation_approvals');
    }
};
