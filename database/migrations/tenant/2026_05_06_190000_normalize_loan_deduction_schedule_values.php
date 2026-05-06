<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames LoanDeductionSchedule enum values to match standard PH
     * payroll terminology (semi-monthly = twice a month, monthly = once
     * a month). Backfills any rows submitted under the previous
     * inverted-but-semantically-broken keys.
     */
    public function up(): void
    {
        DB::table('loan_applications')
            ->where('deduction_schedule', 'monthly_15_30')
            ->update(['deduction_schedule' => 'semi_monthly']);

        DB::table('loan_applications')
            ->where('deduction_schedule', 'twice_monthly_30')
            ->update(['deduction_schedule' => 'monthly']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('loan_applications')
            ->where('deduction_schedule', 'semi_monthly')
            ->update(['deduction_schedule' => 'monthly_15_30']);

        DB::table('loan_applications')
            ->where('deduction_schedule', 'monthly')
            ->update(['deduction_schedule' => 'twice_monthly_30']);
    }
};
