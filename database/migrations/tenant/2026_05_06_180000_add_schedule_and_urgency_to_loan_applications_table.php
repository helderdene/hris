<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds preferred deduction schedule and urgency level (1-5) to loan
     * applications, captured at submission time.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->string('deduction_schedule', 50)->nullable()->after('term_months');
            $table->unsignedTinyInteger('urgency_level')->nullable()->after('deduction_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['deduction_schedule', 'urgency_level']);
        });
    }
};
