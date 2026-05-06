<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores per-level decisions for loan_applications. Mirrors the leave
     * approval table: one row per level per application, plus a per-level
     * SLA deadline so the daily reminder command can target overdue rows
     * without recomputing.
     */
    public function up(): void
    {
        Schema::create('loan_application_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('approval_level');
            $table->string('approver_type', 50);
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('approver_name', 255);
            $table->string('approver_position', 255)->nullable();
            $table->string('decision', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['loan_application_id', 'approval_level'], 'loan_appr_app_level_idx');
            $table->index(['approver_employee_id', 'decision'], 'loan_appr_emp_decision_idx');
            $table->index('decision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_application_approvals');
    }
};
