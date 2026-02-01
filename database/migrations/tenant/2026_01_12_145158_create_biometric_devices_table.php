<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the biometric_devices table for managing MQTT-enabled
     * facial recognition devices with real-time connection status tracking.
     */
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_identifier')->unique();
            $table->foreignId('work_location_id')
                ->constrained('work_locations')
                ->onDelete('cascade');
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('connection_started_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
