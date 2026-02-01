<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the employee_compensations table for storing employee salary
     * and bank account information. One-to-one relationship with employees.
     */
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->unique()
                ->constrained('employees')
                ->onDelete('cascade');

            // Compensation details
            $table->decimal('basic_pay', 12, 2);
            $table->string('currency', 10)->default('PHP');
            $table->string('pay_type');
            $table->date('effective_date');

            // Bank account details (all nullable for initial setup flexibility)
            $table->string('bank_name', 100)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('account_type')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index on employee_id for relationship queries (unique constraint creates index)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_compensations');
    }
};
