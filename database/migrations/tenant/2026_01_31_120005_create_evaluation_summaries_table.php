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
        Schema::create('evaluation_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_participant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Aggregated competency scores by source
            $table->decimal('self_competency_avg', 3, 2)->nullable();
            $table->decimal('manager_competency_avg', 3, 2)->nullable();
            $table->decimal('peer_competency_avg', 3, 2)->nullable();
            $table->decimal('direct_report_competency_avg', 3, 2)->nullable();
            $table->decimal('overall_competency_avg', 3, 2)->nullable();

            // KPI scores
            $table->decimal('kpi_achievement_score', 5, 2)->nullable();
            $table->tinyInteger('manager_kpi_rating')->unsigned()->nullable();

            // Final calibrated scores
            $table->decimal('final_competency_score', 3, 2)->nullable();
            $table->decimal('final_kpi_score', 5, 2)->nullable();
            $table->decimal('final_overall_score', 5, 2)->nullable();
            $table->string('final_rating', 50)->nullable();

            // Calibration metadata
            $table->timestamp('calibrated_at')->nullable();
            $table->unsignedBigInteger('calibrated_by')->nullable();
            $table->text('calibration_notes')->nullable();

            // Employee acknowledgement
            $table->timestamp('employee_acknowledged_at')->nullable();
            $table->text('employee_comments')->nullable();

            $table->timestamps();

            $table->unique('performance_cycle_participant_id', 'unique_participant_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_summaries');
    }
};
