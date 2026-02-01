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
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Loan identification
            $table->string('loan_type', 50);
            $table->string('loan_code', 50)->comment('Unique code per employee for this loan');
            $table->string('reference_number', 100)->nullable()->comment('External reference (e.g., SSS loan number)');

            // Loan amounts
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_rate', 5, 4)->default(0)->comment('Annual interest rate as decimal');
            $table->decimal('monthly_deduction', 12, 2);
            $table->unsignedSmallInteger('term_months')->nullable();

            // Computed totals
            $table->decimal('total_amount', 12, 2)->comment('Principal + interest');
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2);

            // Dates
            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Status
            $table->string('status', 20)->default('active');

            // Additional info
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit (no foreign key - users table is on platform database)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['employee_id', 'loan_code']);
            $table->index(['employee_id', 'status']);
            $table->index(['status', 'loan_type']);
            $table->index('loan_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
