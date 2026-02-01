<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the job_requisitions table for employee job requisition requests.
     * Supports multi-level approval workflow.
     */
    public function up(): void
    {
        Schema::create('job_requisitions', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('position_id')->constrained()->onDelete('restrict');
            $table->foreignId('department_id')->constrained()->onDelete('restrict');
            $table->foreignId('requested_by_employee_id')->constrained('employees')->onDelete('cascade');

            // Reference number for HR tracking (e.g., "JR-2026-00001")
            $table->string('reference_number', 50)->unique();

            // Requisition details
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('employment_type', 30);
            $table->decimal('salary_range_min', 12, 2)->nullable();
            $table->decimal('salary_range_max', 12, 2)->nullable();
            $table->text('justification');
            $table->string('urgency', 20)->default('normal');
            $table->date('preferred_start_date')->nullable();
            $table->json('requirements')->nullable();
            $table->text('remarks')->nullable();

            // Status
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
            $table->index(['requested_by_employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['position_id', 'status']);
            $table->index('urgency');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_requisitions');
    }
};
