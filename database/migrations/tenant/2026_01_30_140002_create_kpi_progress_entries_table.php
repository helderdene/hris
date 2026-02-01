<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the kpi_progress_entries table for tracking KPI progress history.
     * Each entry records a progress update with the value, notes, and who recorded it.
     */
    public function up(): void
    {
        Schema::create('kpi_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_assignment_id');
            $table->decimal('value', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            // recorded_by references platform schema users table - no FK constraint
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            // Foreign key for kpi_assignment
            $table->foreign('kpi_assignment_id', 'kpi_progress_assignment_fk')
                ->references('id')
                ->on('kpi_assignments')
                ->onDelete('cascade');

            // Index for chronological queries
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_progress_entries');
    }
};
