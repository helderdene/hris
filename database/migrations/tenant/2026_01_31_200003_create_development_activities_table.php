<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the development_activities table for tracking specific development activities.
     * Activities belong to development plan items and track completion status.
     */
    public function up(): void
    {
        Schema::create('development_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_plan_item_id');
            $table->string('activity_type'); // training, mentoring, self_study, project, certification, other
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('resource_url', 2048)->nullable(); // Link to course/resource
            $table->date('due_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('development_plan_item_id', 'development_activities_item_fk')
                ->references('id')
                ->on('development_plan_items')
                ->onDelete('cascade');

            // Indexes
            $table->index('activity_type');
            $table->index('is_completed');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_activities');
    }
};
