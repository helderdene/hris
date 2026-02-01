<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goals table for storing both OKR objectives and SMART goals.
     * Uses a discriminator column (goal_type) to distinguish between goal types.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->foreignId('performance_cycle_instance_id')->nullable();
            $table->foreignId('parent_goal_id')->nullable();
            $table->string('goal_type'); // 'okr_objective' or 'smart_goal'
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('visibility')->default('private'); // 'private', 'team', 'organization'
            $table->string('priority')->default('medium'); // 'low', 'medium', 'high', 'critical'
            $table->string('status')->default('draft'); // 'draft', 'pending_approval', 'active', 'completed', 'cancelled'
            $table->string('approval_status')->default('not_required'); // 'not_required', 'pending', 'approved', 'rejected'
            // Note: approved_by does NOT have a FK constraint because users table is in platform database
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('owner_notes')->nullable();
            $table->text('manager_feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('employee_id', 'goals_employee_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
            $table->foreign('performance_cycle_instance_id', 'goals_cycle_instance_fk')
                ->references('id')
                ->on('performance_cycle_instances')
                ->onDelete('set null');
            $table->foreign('parent_goal_id', 'goals_parent_fk')
                ->references('id')
                ->on('goals')
                ->onDelete('set null');

            // Indexes
            $table->index('approved_by');
            $table->index('goal_type');
            $table->index('status');
            $table->index('approval_status');
            $table->index('due_date');
            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
