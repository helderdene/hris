<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the pagibig_contribution_tables table for Pag-IBIG contribution rates.
     * Each table represents a set of contribution tiers effective from a specific date.
     */
    public function up(): void
    {
        Schema::create('pagibig_contribution_tables', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->string('description')->nullable();
            $table->decimal('max_monthly_compensation', 12, 2)->default(5000.00);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('effective_from');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagibig_contribution_tables');
    }
};
