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
        Schema::table('employee_offboardings', function (Blueprint $table) {
            $table->foreignId('coe_document_request_id')->nullable()->after('coe_issued_date')
                ->constrained('document_requests')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_offboardings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coe_document_request_id');
        });
    }
};
