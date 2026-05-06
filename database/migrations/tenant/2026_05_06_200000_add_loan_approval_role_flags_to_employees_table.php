<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds tenant-wide role flags for the 3-step loan approval flow:
     * CFO (Level 1), Loan Admin Manager (Level 2), and Releasing
     * Officer (Level 3, creates the EmployeeLoan on final approval).
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_loan_cfo')->default(false)->after('is_leave_admin_manager');
            $table->boolean('is_loan_admin_manager')->default(false)->after('is_loan_cfo');
            $table->boolean('is_loan_releasing_officer')->default(false)->after('is_loan_admin_manager');

            $table->index('is_loan_cfo');
            $table->index('is_loan_admin_manager');
            $table->index('is_loan_releasing_officer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_loan_cfo']);
            $table->dropIndex(['is_loan_admin_manager']);
            $table->dropIndex(['is_loan_releasing_officer']);
            $table->dropColumn(['is_loan_cfo', 'is_loan_admin_manager', 'is_loan_releasing_officer']);
        });
    }
};
