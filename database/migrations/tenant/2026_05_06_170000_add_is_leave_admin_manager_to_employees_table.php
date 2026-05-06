<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a flag designating an employee as the tenant-wide Admin Manager
     * for leave approvals (level 2 of the approval chain).
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_leave_admin_manager')->default(false)->after('employment_status');
            $table->index('is_leave_admin_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_leave_admin_manager']);
            $table->dropColumn('is_leave_admin_manager');
        });
    }
};
