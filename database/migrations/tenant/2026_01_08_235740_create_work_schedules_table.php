<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the work_schedules table for managing work schedule configurations.
     * Supports Fixed, Flexible, Shifting, and Compressed schedule types.
     */
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('schedule_type');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('time_configuration')->nullable();
            $table->json('overtime_rules')->nullable();
            $table->json('night_differential')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('schedule_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
