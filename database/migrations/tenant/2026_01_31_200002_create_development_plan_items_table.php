<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the development_plan_items table for individual development areas.
     * Items can be linked to competencies for gap-based tracking.
     */
    public function up(): void
    {
        Schema::create('development_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_plan_id');
            $table->foreignId('competency_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('current_level')->nullable(); // 1-5 proficiency
            $table->unsignedTinyInteger('target_level')->nullable(); // 1-5 proficiency
            $table->string('priority')->default('medium'); // high, medium, low
            $table->string('status')->default('not_started'); // not_started, in_progress, completed
            $table->unsignedTinyInteger('progress_percentage')->default(0); // 0-100
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('development_plan_id', 'development_plan_items_plan_fk')
                ->references('id')
                ->on('development_plans')
                ->onDelete('cascade');
            $table->foreign('competency_id', 'development_plan_items_competency_fk')
                ->references('id')
                ->on('competencies')
                ->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_plan_items');
    }
};
