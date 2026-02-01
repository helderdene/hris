<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goal_progress_entries table for tracking progress updates
     * on goals and key results over time.
     */
    public function up(): void
    {
        Schema::create('goal_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id');
            $table->foreignId('goal_key_result_id')->nullable();
            $table->decimal('progress_value', 15, 2)->nullable();
            $table->decimal('progress_percentage', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            // Note: recorded_by does NOT have a FK constraint because users table is in platform database
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('goal_id', 'goal_progress_goal_fk')
                ->references('id')
                ->on('goals')
                ->onDelete('cascade');
            $table->foreign('goal_key_result_id', 'goal_progress_kr_fk')
                ->references('id')
                ->on('goal_key_results')
                ->onDelete('cascade');

            // Indexes
            $table->index('recorded_by');
            $table->index(['goal_id', 'recorded_at']);
            $table->index(['goal_key_result_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_progress_entries');
    }
};
