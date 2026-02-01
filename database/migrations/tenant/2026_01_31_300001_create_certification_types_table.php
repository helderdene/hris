<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the certification_types table for defining types of professional
     * certifications and licenses that employees can hold. Includes configuration
     * for validity periods and expiry reminder schedules.
     */
    public function up(): void
    {
        Schema::create('certification_types', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->text('description')->nullable();

            // Validity Configuration
            $table->unsignedInteger('validity_period_months')->nullable();
            $table->json('reminder_days_before_expiry')->nullable(); // [90, 60, 30]

            // Settings
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('is_mandatory');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_types');
    }
};
