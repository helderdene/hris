<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_assessments table for assessment questions.
     * Stores multiple choice, true/false, and multi-select questions.
     */
    public function up(): void
    {
        Schema::create('compliance_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_module_id')->constrained('compliance_modules')->cascadeOnDelete();
            $table->text('question');
            $table->string('question_type', 50);
            $table->json('options');
            $table->json('correct_answers');
            $table->integer('points')->default(1);
            $table->text('explanation')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('compliance_module_id');
            $table->index('question_type');
            $table->index('sort_order');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_assessments');
    }
};
