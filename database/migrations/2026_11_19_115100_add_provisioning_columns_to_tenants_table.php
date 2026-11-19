<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A null status means the tenant is fully provisioned, so existing tenants
     * and tenants created outside registration need no backfill.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('provisioning_status', 20)->nullable()->index()->after('timezone');
            $table->foreignId('provisioning_user_id')->nullable()->after('provisioning_status')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provisioning_user_id');
            $table->dropColumn('provisioning_status');
        });
    }
};
