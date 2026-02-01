<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_progress table for module-level progress tracking.
     * Tracks individual module completion status and time spent.
     */
    public function up(): void
    {
        Schema::create('compliance_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_assignment_id')->constrained('compliance_assignments')->cascadeOnDelete();
            $table->foreignId('compliance_module_id')->constrained('compliance_modules')->cascadeOnDelete();
            $table->string('status', 50)->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('time_spent_minutes')->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->json('position_data')->nullable();
            $table->decimal('best_score', 5, 2)->nullable();
            $table->integer('attempts_made')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['compliance_assignment_id', 'compliance_module_id'], 'compliance_progress_unique');
            $table->index('compliance_assignment_id');
            $table->index('compliance_module_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_progress');
    }
};
