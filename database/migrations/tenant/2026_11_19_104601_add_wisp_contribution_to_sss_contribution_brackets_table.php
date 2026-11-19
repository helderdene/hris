<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the WISP (Workers' Investment and Savings Program) amount to each SSS bracket.
     */
    public function up(): void
    {
        Schema::table('sss_contribution_brackets', function (Blueprint $table) {
            $table->decimal('wisp_contribution', 10, 2)->default(0)->after('ec_contribution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sss_contribution_brackets', function (Blueprint $table) {
            $table->dropColumn('wisp_contribution');
        });
    }
};
