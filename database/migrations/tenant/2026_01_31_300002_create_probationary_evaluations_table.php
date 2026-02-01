<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the probationary_evaluations table for tracking employee
     * evaluations at 3rd and 5th month milestones.
     */
    public function up(): void
    {
        Schema::create('probationary_evaluations', function (Blueprint $table) {
            $table->id();

            // Employee being evaluated
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            // Link to performance cycle participant if applicable
            $table->unsignedBigInteger('performance_cycle_participant_id')->nullable();
            $table->foreign('performance_cycle_participant_id', 'prob_eval_participant_fk')
                ->references('id')
                ->on('performance_cycle_participants')
                ->onDelete('set null');

            // Evaluator (manager)
            $table->foreignId('evaluator_id')->constrained('employees')->onDelete('cascade');
            $table->string('evaluator_name')->nullable();
            $table->string('evaluator_position')->nullable();

            // Milestone tracking
            $table->string('milestone', 20);
            $table->date('milestone_date');
            $table->date('due_date');

            // Link to previous evaluation (for 5th month showing 3rd month results)
            $table->foreignId('previous_evaluation_id')->nullable()->constrained('probationary_evaluations')->onDelete('set null');

            // Status
            $table->string('status', 30)->default('pending');

            // Criteria ratings (JSON array of {criteria_id, rating, comments})
            $table->json('criteria_ratings')->nullable();

            // Overall assessment
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('manager_comments')->nullable();

            // Regularization recommendation (for 5th month or extension evaluation)
            $table->string('recommendation', 30)->nullable();
            $table->text('recommendation_conditions')->nullable();
            $table->smallInteger('extension_months')->nullable();
            $table->text('recommendation_reason')->nullable();

            // Timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('evaluator_id');
            $table->index(['employee_id', 'milestone']);
            $table->index('status');
            $table->index('milestone_date');
            $table->index('due_date');

            // Unique constraint: one evaluation per employee per milestone
            $table->unique(['employee_id', 'milestone'], 'probationary_eval_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probationary_evaluations');
    }
};
