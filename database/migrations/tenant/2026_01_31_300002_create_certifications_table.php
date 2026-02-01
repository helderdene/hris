<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the certifications table for tracking employee professional
     * certifications and licenses. Supports a workflow from draft through
     * approval to active status, with automatic expiry tracking.
     */
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();

            // Employee reference
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');

            // Certification type reference
            $table->foreignId('certification_type_id')
                ->constrained('certification_types')
                ->onDelete('restrict');

            // Certification details
            $table->string('certificate_number')->nullable();
            $table->string('issuing_body');
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->text('description')->nullable();

            // Workflow status
            $table->string('status', 50)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('revocation_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_id');
            $table->index('certification_type_id');
            $table->index('status');
            $table->index('expiry_date');
            $table->index(['employee_id', 'status']);
            $table->index(['status', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
