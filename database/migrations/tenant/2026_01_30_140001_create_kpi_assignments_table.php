<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the kpi_assignments table for storing KPI assignments to performance
     * cycle participants. Each assignment links a KPI template to a specific
     * participant with individual targets and tracking.
     */
    public function up(): void
    {
        Schema::create('kpi_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id');
            $table->foreignId('performance_cycle_participant_id');
            $table->decimal('target_value', 15, 2);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->decimal('achievement_percentage', 8, 2)->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign keys with explicit shorter names
            $table->foreign('kpi_template_id', 'kpi_assign_template_fk')
                ->references('id')
                ->on('kpi_templates')
                ->onDelete('cascade');
            $table->foreign('performance_cycle_participant_id', 'kpi_assign_participant_fk')
                ->references('id')
                ->on('performance_cycle_participants')
                ->onDelete('cascade');

            // Unique constraint to prevent duplicate KPI assignments per participant
            $table->unique(
                ['kpi_template_id', 'performance_cycle_participant_id'],
                'kpi_assignment_unique'
            );

            // Indexes for common queries
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_assignments');
    }
};
