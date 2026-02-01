<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the withholding_tax_brackets table for BIR tax brackets.
     * Each bracket defines the tax computation for a compensation range.
     */
    public function up(): void
    {
        Schema::create('withholding_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withholding_tax_table_id')
                ->constrained('withholding_tax_tables')
                ->onDelete('cascade');
            $table->decimal('min_compensation', 12, 2);
            $table->decimal('max_compensation', 12, 2)->nullable();
            $table->decimal('base_tax', 12, 2);
            $table->decimal('excess_rate', 5, 4);
            $table->timestamps();

            $table->index('withholding_tax_table_id');
            $table->index(['min_compensation', 'max_compensation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withholding_tax_brackets');
    }
};
