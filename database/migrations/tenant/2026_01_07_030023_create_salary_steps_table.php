<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the salary_steps table with one-to-many relationship to salary grades.
     * Steps are ordered by step_number within each grade.
     */
    public function up(): void
    {
        Schema::create('salary_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_grade_id')
                ->constrained('salary_grades')
                ->onDelete('cascade');
            $table->unsignedInteger('step_number');
            $table->decimal('amount', 12, 2);
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique(['salary_grade_id', 'step_number']);
            $table->index('salary_grade_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_steps');
    }
};
