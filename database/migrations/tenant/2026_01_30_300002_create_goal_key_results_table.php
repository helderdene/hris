<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goal_key_results table for storing measurable key results
     * associated with OKR-type goals.
     */
    public function up(): void
    {
        Schema::create('goal_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('metric_type'); // 'number', 'percentage', 'currency', 'boolean'
            $table->string('metric_unit', 50)->nullable();
            $table->decimal('target_value', 15, 2);
            $table->decimal('starting_value', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->nullable();
            $table->decimal('achievement_percentage', 8, 2)->nullable();
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->string('status')->default('pending'); // 'pending', 'in_progress', 'completed'
            $table->timestamp('completed_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign key
            $table->foreign('goal_id', 'goal_kr_goal_fk')
                ->references('id')
                ->on('goals')
                ->onDelete('cascade');

            // Indexes
            $table->index('status');
            $table->index(['goal_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_key_results');
    }
};
