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
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('source')->default('biometric')->index()->after('raw_payload');
            $table->foreignId('kiosk_id')->nullable()->after('source')
                ->constrained()->nullOnDelete();

            // Make biometric_device_id nullable for kiosk/self-service logs
            $table->unsignedBigInteger('biometric_device_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['kiosk_id']);
            $table->dropColumn(['source', 'kiosk_id']);

            $table->unsignedBigInteger('biometric_device_id')->nullable(false)->change();
        });
    }
};
