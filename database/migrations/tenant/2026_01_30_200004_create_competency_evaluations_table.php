<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the competency_evaluations table for tracking
     * competency assessments within performance cycles.
     * Supports self-rating, manager rating, and final rating.
     */
    public function up(): void
    {
        Schema::create('competency_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_participant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('position_competency_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->tinyInteger('self_rating')->nullable();
            $table->text('self_comments')->nullable();
            $table->tinyInteger('manager_rating')->nullable();
            $table->text('manager_comments')->nullable();
            $table->tinyInteger('final_rating')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            // Unique constraint: one evaluation per participant per position competency
            $table->unique(
                ['performance_cycle_participant_id', 'position_competency_id'],
                'competency_eval_participant_position_unique'
            );

            $table->index('self_rating');
            $table->index('manager_rating');
            $table->index('final_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competency_evaluations');
    }
};
