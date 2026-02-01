<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the proficiency_levels table for storing the 1-5 rating scale definitions.
     * Each level has a name, description, and behavioral indicators.
     */
    public function up(): void
    {
        Schema::create('proficiency_levels', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('level')->unique();
            $table->string('name', 50);
            $table->text('description');
            $table->json('behavioral_indicators')->nullable();
            $table->timestamps();

            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proficiency_levels');
    }
};
