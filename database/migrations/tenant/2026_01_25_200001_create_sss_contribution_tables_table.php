<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the sss_contribution_tables table for SSS contribution rates.
     * Each table represents a set of contribution brackets effective from a specific date.
     */
    public function up(): void
    {
        Schema::create('sss_contribution_tables', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->string('description')->nullable();
            $table->decimal('employee_rate', 5, 4)->default(0.0450);
            $table->decimal('employer_rate', 5, 4)->default(0.0950);
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
        Schema::dropIfExists('sss_contribution_tables');
    }
};
