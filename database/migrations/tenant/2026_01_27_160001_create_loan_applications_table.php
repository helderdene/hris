<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->string('loan_type');
            $table->decimal('amount_requested', 12, 2);
            $table->integer('term_months');
            $table->text('purpose')->nullable();
            $table->json('documents')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('reviewer_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('employee_loan_id')->nullable()->constrained('employee_loans')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
