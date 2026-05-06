<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks chain progress + overall SLA on loan_applications. Per-step
     * deadlines live on loan_application_approvals.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->smallInteger('current_approval_level')->default(0)->after('status');
            $table->smallInteger('total_approval_levels')->default(0)->after('current_approval_level');
            $table->timestamp('sla_deadline_at')->nullable()->after('total_approval_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'current_approval_level',
                'total_approval_levels',
                'sla_deadline_at',
            ]);
        });
    }
};
