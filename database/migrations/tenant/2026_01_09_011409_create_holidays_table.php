<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the holidays table for Philippine holiday calendar management.
     * Supports national holidays and location-specific regional holidays.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->string('holiday_type');
            $table->text('description')->nullable();
            $table->boolean('is_national')->default(true);
            $table->integer('year');
            $table->foreignId('work_location_id')
                ->nullable()
                ->constrained('work_locations')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Index on date column for efficient calendar queries
            $table->index('date');

            // Index on year column for year-based filtering
            $table->index('year');

            // Composite index on (year, is_national) for common query pattern
            $table->index(['year', 'is_national']);

            // Index on work_location_id for location-specific queries
            $table->index('work_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
