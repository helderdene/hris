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
        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->timestamp('host_approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('host_approved_by')->nullable()->after('host_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visitor_visits', function (Blueprint $table) {
            $table->dropColumn(['host_approved_at', 'host_approved_by']);
        });
    }
};
