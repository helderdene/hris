<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the probationary_criteria_templates table for configurable
     * evaluation criteria per milestone.
     */
    public function up(): void
    {
        Schema::create('probationary_criteria_templates', function (Blueprint $table) {
            $table->id();

            // Milestone this criteria applies to
            $table->string('milestone', 20);

            // Criteria details
            $table->string('name');
            $table->text('description')->nullable();
            $table->smallInteger('weight')->default(1);
            $table->smallInteger('sort_order')->default(0);

            // Rating scale configuration
            $table->smallInteger('min_rating')->default(1);
            $table->smallInteger('max_rating')->default(5);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('milestone');
            $table->index(['milestone', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probationary_criteria_templates');
    }
};
