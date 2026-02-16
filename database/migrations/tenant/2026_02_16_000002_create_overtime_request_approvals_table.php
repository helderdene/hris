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
        Schema::create('overtime_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained('overtime_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('approval_level');
            $table->string('approver_type');
            $table->foreignId('approver_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('approver_name');
            $table->string('approver_position')->nullable();
            $table->string('decision')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['overtime_request_id', 'approval_level'], 'ot_approvals_request_level_index');
            $table->index(['approver_employee_id', 'decision'], 'ot_approvals_approver_decision_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approvals');
    }
};
