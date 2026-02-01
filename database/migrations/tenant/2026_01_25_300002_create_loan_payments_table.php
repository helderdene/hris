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
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            // No foreign key - payroll_deductions table may not exist yet
            $table->unsignedBigInteger('payroll_deduction_id')->nullable();

            // Payment details
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);

            // Payment info
            $table->date('payment_date');
            $table->string('payment_source', 50)->default('payroll')->comment('payroll, manual, adjustment');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['employee_loan_id', 'payment_date']);
            $table->index('payment_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
