<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_assessment_attempts table for assessment records.
     * Stores individual assessment attempt details including answers and scores.
     */
    public function up(): void
    {
        Schema::create('compliance_assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_progress_id')->constrained('compliance_progress')->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('answers');
            $table->integer('correct_count')->default(0);
            $table->integer('total_questions')->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->default(false);
            $table->integer('time_taken_minutes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('compliance_progress_id');
            $table->index('attempt_number');
            $table->index('passed');
            $table->index(['compliance_progress_id', 'attempt_number'], 'compliance_attempt_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_assessment_attempts');
    }
};
