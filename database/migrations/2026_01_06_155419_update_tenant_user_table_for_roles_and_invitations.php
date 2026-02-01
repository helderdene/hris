<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            // Update existing 'member' roles to 'employee'
            // This must be done before changing the default
        });

        // Update existing data: change 'member' to 'employee'
        DB::table('tenant_user')
            ->where('role', 'member')
            ->update(['role' => 'employee']);

        Schema::table('tenant_user', function (Blueprint $table) {
            // Change default from 'member' to 'employee'
            $table->string('role')->default('employee')->change();

            // Add invitation tracking fields
            $table->timestamp('invited_at')->nullable()->after('role');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
            $table->string('invitation_token', 64)->nullable()->after('invitation_accepted_at');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');

            // Add index on invitation_token for lookup performance
            $table->index('invitation_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            // Remove the index first
            $table->dropIndex(['invitation_token']);

            // Remove invitation tracking fields
            $table->dropColumn([
                'invited_at',
                'invitation_accepted_at',
                'invitation_token',
                'invitation_expires_at',
            ]);

            // Change default back to 'member'
            $table->string('role')->default('member')->change();
        });

        // Update existing data: change 'employee' back to 'member'
        DB::table('tenant_user')
            ->where('role', 'employee')
            ->update(['role' => 'member']);
    }
};
