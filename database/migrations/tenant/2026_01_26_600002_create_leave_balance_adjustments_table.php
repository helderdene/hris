<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the leave_balance_adjustments table for audit trail of balance changes.
     * Records all manual adjustments made by HR with reason and previous/new values.
     */
    public function up(): void
    {
        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('leave_balance_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('adjusted_by')->nullable(); // User who made the adjustment

            // Adjustment Details
            $table->string('adjustment_type', 20); // credit, debit
            $table->decimal('days', 6, 2);         // Absolute value (always positive)
            $table->text('reason');                // Explanation for the adjustment

            // Audit Trail
            $table->decimal('previous_balance', 6, 2); // Balance before adjustment
            $table->decimal('new_balance', 6, 2);      // Balance after adjustment

            // Optional Reference (e.g., leave request, year-end processing)
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('leave_balance_id');
            $table->index('adjusted_by');
            $table->index('adjustment_type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_adjustments');
    }
};
