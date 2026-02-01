<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the pagibig_contribution_tiers table for Pag-IBIG contribution tiers.
     * Each tier defines the contribution rates for a salary range.
     */
    public function up(): void
    {
        Schema::create('pagibig_contribution_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagibig_contribution_table_id')
                ->constrained('pagibig_contribution_tables')
                ->onDelete('cascade');
            $table->decimal('min_salary', 12, 2);
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->decimal('employee_rate', 5, 4);
            $table->decimal('employer_rate', 5, 4);
            $table->timestamps();

            $table->index('pagibig_contribution_table_id');
            $table->index(['min_salary', 'max_salary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagibig_contribution_tiers');
    }
};
