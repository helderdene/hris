<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the leave_types table for leave type configuration.
     * Supports Philippine statutory leaves and custom company leave types
     * with configurable accrual rules, carry-over settings, and cash conversion.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('leave_category', 50); // statutory, company, special

            // Accrual Configuration
            $table->string('accrual_method', 50); // annual, monthly, tenure_based, none
            $table->decimal('default_days_per_year', 5, 2)->default(0);
            $table->decimal('monthly_accrual_rate', 5, 4)->nullable();
            $table->json('tenure_brackets')->nullable(); // [{years: 1, days: 5}, {years: 3, days: 7}]

            // Carry-over Settings
            $table->boolean('allow_carry_over')->default(false);
            $table->decimal('max_carry_over_days', 5, 2)->nullable();
            $table->integer('carry_over_expiry_months')->nullable();

            // Cash Conversion Settings
            $table->boolean('is_convertible_to_cash')->default(false);
            $table->decimal('cash_conversion_rate', 5, 4)->nullable();
            $table->decimal('max_convertible_days', 5, 2)->nullable();

            // Eligibility Rules
            $table->integer('min_tenure_months')->nullable();
            $table->json('eligible_employment_types')->nullable(); // ['regular', 'probationary'] or null for all
            $table->string('gender_restriction', 20)->nullable(); // male, female, or null

            // Additional Settings
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->integer('max_consecutive_days')->nullable();
            $table->integer('min_days_advance_notice')->nullable();

            // Statutory Tracking
            $table->boolean('is_statutory')->default(false);
            $table->string('statutory_reference', 100)->nullable(); // 'RA 11210', 'Labor Code Art. 95'
            $table->boolean('is_template')->default(false);

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('leave_category');
            $table->index('is_active');
            $table->index('is_statutory');
            $table->index(['leave_category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
