<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the philhealth_contribution_tables table for PhilHealth contribution rates.
     * PhilHealth uses a percentage-based calculation with floor and ceiling limits.
     */
    public function up(): void
    {
        Schema::create('philhealth_contribution_tables', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->string('description')->nullable();
            $table->decimal('contribution_rate', 5, 4)->default(0.0500);
            $table->decimal('employee_share_rate', 5, 4)->default(0.5000);
            $table->decimal('employer_share_rate', 5, 4)->default(0.5000);
            $table->decimal('salary_floor', 12, 2)->default(10000.00);
            $table->decimal('salary_ceiling', 12, 2)->default(100000.00);
            $table->decimal('min_contribution', 10, 2)->default(500.00);
            $table->decimal('max_contribution', 10, 2)->default(5000.00);
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
        Schema::dropIfExists('philhealth_contribution_tables');
    }
};
