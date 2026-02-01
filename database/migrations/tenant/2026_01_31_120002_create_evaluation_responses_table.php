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
        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_reviewer_id')
                ->constrained()
                ->cascadeOnDelete();

            // Narrative feedback sections
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('overall_comments')->nullable();
            $table->text('development_suggestions')->nullable();

            // Draft and submission tracking
            $table->boolean('is_draft')->default(true);
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique('evaluation_reviewer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_responses');
    }
};
