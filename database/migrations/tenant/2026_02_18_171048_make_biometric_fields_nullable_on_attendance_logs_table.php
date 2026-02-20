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
            $table->string('device_record_id')->nullable()->change();
            $table->string('employee_code')->nullable()->change();
            $table->decimal('confidence', 5, 2)->nullable()->change();
            $table->string('verify_status')->nullable()->change();
            $table->string('device_person_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('device_record_id')->nullable(false)->change();
            $table->string('employee_code')->nullable(false)->change();
            $table->decimal('confidence', 5, 2)->nullable(false)->change();
            $table->string('verify_status')->nullable(false)->change();
            $table->string('device_person_id')->nullable(false)->change();
        });
    }
};
