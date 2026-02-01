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
        Schema::create('payroll_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_entry_id')->constrained()->cascadeOnDelete();

            // Earning details
            $table->string('earning_type', 30);
            $table->string('earning_code', 50)->nullable();
            $table->string('description');

            // Calculation fields
            $table->decimal('quantity', 10, 4)->default(0);
            $table->string('quantity_unit', 20)->nullable();
            $table->decimal('rate', 12, 4)->default(0);
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->decimal('amount', 12, 2)->default(0);

            // Tax handling
            $table->boolean('is_taxable')->default(true);
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Index for aggregations
            $table->index(['payroll_entry_id', 'earning_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_earnings');
    }
};
