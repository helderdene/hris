<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the development_plans table for individual development plans.
     * Plans can be created standalone or linked to an evaluation via performance_cycle_participant_id.
     */
    public function up(): void
    {
        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->foreignId('performance_cycle_participant_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, pending_approval, approved, in_progress, completed, cancelled
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('career_path_notes')->nullable();
            $table->foreignId('manager_id')->nullable();
            // Note: approved_by and created_by do NOT have FK constraints because users table is in platform database
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('employee_id', 'development_plans_employee_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
            $table->foreign('performance_cycle_participant_id', 'development_plans_participant_fk')
                ->references('id')
                ->on('performance_cycle_participants')
                ->onDelete('set null');
            $table->foreign('manager_id', 'development_plans_manager_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('set null');

            // Indexes
            $table->index('approved_by');
            $table->index('created_by');
            $table->index('status');
            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_plans');
    }
};
