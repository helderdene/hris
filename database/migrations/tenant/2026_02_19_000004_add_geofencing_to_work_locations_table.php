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
        Schema::table('work_locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('metadata');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius')->nullable()->after('longitude');
            $table->json('ip_whitelist')->nullable()->after('geofence_radius');
            $table->string('location_check')->default('none')->after('ip_whitelist');
            $table->boolean('self_service_clockin_enabled')->default(false)->after('location_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_locations', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'geofence_radius',
                'ip_whitelist',
                'location_check',
                'self_service_clockin_enabled',
            ]);
        });
    }
};
