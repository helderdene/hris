<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the sss_contribution_brackets table for SSS contribution brackets.
     * Each bracket defines the contribution amounts for a salary range.
     */
    public function up(): void
    {
        Schema::create('sss_contribution_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sss_contribution_table_id')
                ->constrained('sss_contribution_tables')
                ->onDelete('cascade');
            $table->decimal('min_salary', 12, 2);
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->decimal('monthly_salary_credit', 12, 2);
            $table->decimal('employee_contribution', 10, 2);
            $table->decimal('employer_contribution', 10, 2);
            $table->decimal('total_contribution', 10, 2);
            $table->decimal('ec_contribution', 10, 2)->default(0);
            $table->timestamps();

            $table->index('sss_contribution_table_id');
            $table->index(['min_salary', 'max_salary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sss_contribution_brackets');
    }
};
