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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('applied')->index();
            $table->string('source')->default('careers_page');
            $table->text('cover_letter')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            // created_by references platform schema users table - no FK constraint
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('screening_at')->nullable();
            $table->timestamp('interview_at')->nullable();
            $table->timestamp('assessment_at')->nullable();
            $table->timestamp('offer_at')->nullable();
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'job_posting_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
