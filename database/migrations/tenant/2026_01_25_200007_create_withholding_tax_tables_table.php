<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the withholding_tax_tables table for BIR withholding tax rates.
     * Each table represents a set of tax brackets effective from a specific date
     * for a specific pay period (daily, weekly, semi-monthly, monthly).
     */
    public function up(): void
    {
        Schema::create('withholding_tax_tables', function (Blueprint $table) {
            $table->id();
            $table->string('pay_period', 20);
            $table->date('effective_from');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pay_period', 'effective_from']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_tables');
    }
};
