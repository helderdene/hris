<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the positions table for job title management.
     * Positions can be linked to salary grades for compensation structure.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('salary_grade_id')
                ->nullable()
                ->constrained('salary_grades')
                ->onDelete('set null');
            $table->string('job_level');
            $table->string('employment_type');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('salary_grade_id');
            $table->index('status');
            $table->index('job_level');
            $table->index('employment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
