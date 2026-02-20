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
        Schema::create('visitor_visits', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Visit details
            $table->string('purpose', 500);
            $table->string('status', 50)->default('pending_approval');
            $table->string('registration_source', 50);

            // Scheduling
            $table->timestamp('expected_at')->nullable();

            // Approval workflow
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            // Check-in/out
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('check_in_method', 50)->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->foreignId('biometric_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kiosk_id')->nullable()->constrained()->nullOnDelete();

            // Tokens
            $table->string('qr_token', 64)->unique()->nullable();
            $table->string('registration_token', 64)->unique();

            // Additional
            $table->string('badge_number', 50)->nullable();
            $table->timestamp('host_notified_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('work_location_id');
            $table->index('expected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_visits');
    }
};
