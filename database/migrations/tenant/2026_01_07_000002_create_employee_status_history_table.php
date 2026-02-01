<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a table to track employee status changes over time.
     * Only active status records have ended_at as null.
     *
     * Note: changed_by references platform schema users table - no FK constraint
     * since it's a cross-database reference.
     */
    public function up(): void
    {
        Schema::create('employee_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            // changed_by references platform schema users table - no FK constraint
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->datetime('ended_at')->nullable();
            $table->timestamps();

            // Index for efficient current status queries (ended_at is null)
            $table->index(['employee_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_status_history');
    }
};
