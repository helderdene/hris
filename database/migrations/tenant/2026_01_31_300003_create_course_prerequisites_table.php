<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the course_prerequisites pivot table for self-referential
     * many-to-many relationship between courses.
     */
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_id')->constrained('courses')->cascadeOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'prerequisite_id']);
            $table->index('prerequisite_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
