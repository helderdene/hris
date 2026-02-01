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
        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();

            // Deduction details
            $table->string('deduction_type', 30);
            $table->string('deduction_code', 50)->nullable();
            $table->string('description');

            // Calculation fields
            $table->decimal('basis_amount', 12, 2)->default(0);
            $table->decimal('rate', 8, 6)->default(0);
            $table->decimal('amount', 12, 2)->default(0);

            // Share classification
            $table->boolean('is_employee_share')->default(true);
            $table->boolean('is_employer_share')->default(false);
            $table->text('remarks')->nullable();

            // Reference to contribution tables for audit trail
            $table->string('contribution_table_type', 50)->nullable();
            $table->unsignedBigInteger('contribution_table_id')->nullable();

            $table->timestamps();

            // Index for aggregations
            $table->index(['payroll_entry_id', 'deduction_type']);
            $table->index(['deduction_type', 'is_employee_share']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};
