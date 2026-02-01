<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the leave_applications table for employee leave requests.
     * Supports multi-level approval workflow with balance tracking.
     */
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('restrict');
            $table->foreignId('leave_balance_id')->nullable()->constrained()->onDelete('set null');

            // Reference number for HR tracking (e.g., "LV-2026-00001")
            $table->string('reference_number', 50)->unique();

            // Leave dates
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 2);
            $table->boolean('is_half_day_start')->default(false);
            $table->boolean('is_half_day_end')->default(false);

            // Request details
            $table->text('reason');
            $table->string('status', 30)->default('draft');

            // Approval chain tracking
            $table->smallInteger('current_approval_level')->default(0);
            $table->smallInteger('total_approval_levels')->default(1);

            // Status timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Audit trail and metadata
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('reference_number');
            $table->index(['employee_id', 'status']);
            $table->index(['employee_id', 'start_date', 'end_date']);
            $table->index(['leave_type_id', 'status']);
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
