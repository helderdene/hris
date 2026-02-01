<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the position_competencies table for the matrix linking
     * competencies to positions by job level. This allows different
     * proficiency expectations for the same competency based on job level.
     */
    public function up(): void
    {
        Schema::create('position_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->string('job_level', 50);
            $table->tinyInteger('required_proficiency_level')->default(3);
            $table->boolean('is_mandatory')->default(true);
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'competency_id', 'job_level'], 'position_competency_level_unique');
            $table->index('job_level');
            $table->index('is_mandatory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_competencies');
    }
};
