<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the payroll_cycles table for storing payroll cycle definitions.
     * Each cycle represents a recurring payroll pattern (e.g., semi-monthly, monthly)
     * with configurable cutoff rules.
     */
    public function up(): void
    {
        Schema::create('payroll_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('cycle_type');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('cutoff_rules')->nullable();
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
        Schema::dropIfExists('payroll_cycles');
    }
};
