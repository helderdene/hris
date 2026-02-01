<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the salary_grades table for compensation structure.
     * Validation: minimum <= midpoint <= maximum is enforced at model level.
     */
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('minimum_salary', 12, 2);
            $table->decimal('midpoint_salary', 12, 2);
            $table->decimal('maximum_salary', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
