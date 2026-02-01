<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the performance_cycles table for storing performance cycle configurations.
     * Each cycle represents a recurring performance evaluation pattern (e.g., annual, mid-year)
     * that can be used to generate specific evaluation instances.
     */
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('cycle_type');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Index for common queries
            $table->index('cycle_type');
            $table->index('status');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_cycles');
    }
};
