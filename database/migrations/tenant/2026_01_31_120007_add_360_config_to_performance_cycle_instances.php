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
        Schema::table('performance_cycle_instances', function (Blueprint $table) {
            $table->boolean('enable_360_feedback')->default(true)->after('notes');
            $table->boolean('enable_peer_review')->default(true)->after('enable_360_feedback');
            $table->boolean('enable_direct_report_review')->default(true)->after('enable_peer_review');
            $table->date('self_evaluation_deadline')->nullable()->after('enable_direct_report_review');
            $table->date('peer_review_deadline')->nullable()->after('self_evaluation_deadline');
            $table->date('manager_review_deadline')->nullable()->after('peer_review_deadline');
            $table->date('calibration_deadline')->nullable()->after('manager_review_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_cycle_instances', function (Blueprint $table) {
            $table->dropColumn([
                'enable_360_feedback',
                'enable_peer_review',
                'enable_direct_report_review',
                'self_evaluation_deadline',
                'peer_review_deadline',
                'manager_review_deadline',
                'calibration_deadline',
            ]);
        });
    }
};
