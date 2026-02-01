<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the course_category_course pivot table for many-to-many
     * relationship between courses and categories.
     */
    public function up(): void
    {
        Schema::create('course_category_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'course_category_id']);
            $table->index('course_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_category_course');
    }
};
