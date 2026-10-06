<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a flag designating an employee as a level 2 overtime approver.
     * Multiple employees may be flagged; any one of them can decide.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_overtime_approver')->default(false)->after('is_leave_admin_manager');
            $table->index('is_overtime_approver');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_overtime_approver']);
            $table->dropColumn('is_overtime_approver');
        });
    }
};
