<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the job_postings table for public job listings.
 *
 * Job postings can optionally be linked to an approved job requisition.
 * They go through a Draft -> Published -> Closed -> Archived lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_requisition_id')->nullable()->constrained('job_requisitions')->onDelete('set null');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->foreignId('created_by_employee_id')->constrained('employees')->onDelete('cascade');

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->longText('requirements')->nullable();
            $table->longText('benefits')->nullable();
            $table->string('employment_type');
            $table->string('location');
            $table->string('salary_display_option')->default('hidden');
            $table->decimal('salary_range_min', 12, 2)->nullable();
            $table->decimal('salary_range_max', 12, 2)->nullable();
            $table->text('application_instructions')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('slug');
            $table->index(['status', 'published_at']);
            $table->index('department_id');
            $table->index('job_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
