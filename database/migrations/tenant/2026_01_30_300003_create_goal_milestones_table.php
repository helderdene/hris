<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goal_milestones table for storing milestones
     * associated with SMART-type goals.
     */
    public function up(): void
    {
        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            // Note: completed_by does NOT have a FK constraint because users table is in platform database
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('goal_id', 'goal_milestone_goal_fk')
                ->references('id')
                ->on('goals')
                ->onDelete('cascade');

            // Indexes
            $table->index('completed_by');
            $table->index(['goal_id', 'sort_order']);
            $table->index('is_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_milestones');
    }
};
