<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();

            // Device reference
            $table->foreignId('biometric_device_id')->constrained()->cascadeOnDelete();

            // Employee reference (nullable for unmatched logs)
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();

            // Device-provided identifiers
            $table->string('device_person_id');
            $table->string('device_record_id');
            $table->string('employee_code');

            // Verification data
            $table->decimal('confidence', 5, 2);
            $table->string('verify_status');

            // Timestamp from device
            $table->timestamp('logged_at');

            // Additional metadata
            $table->string('direction')->nullable();
            $table->string('person_name')->nullable();

            // Captured photo (base64, can be large)
            $table->longText('captured_photo')->nullable();

            // Raw payload for debugging
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('employee_id');
            $table->index('logged_at');
            $table->index(['employee_id', 'logged_at']);
            $table->index('employee_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
