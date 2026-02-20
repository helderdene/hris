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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('kiosk_pin')->nullable()->after('work_history');
            $table->string('kiosk_pin_hash')->nullable()->unique()->after('kiosk_pin');
            $table->timestamp('kiosk_pin_changed_at')->nullable()->after('kiosk_pin_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['kiosk_pin', 'kiosk_pin_hash', 'kiosk_pin_changed_at']);
        });
    }
};
