<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the performance_cycle_participants table for storing employee assignments
     * to performance cycle instances. Links employees with their reviewing managers.
     */
    public function up(): void
    {
        Schema::create('performance_cycle_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_instance_id');
            $table->foreignId('employee_id');
            $table->foreignId('manager_id')->nullable();

            // Foreign keys with explicit shorter names
            $table->foreign('performance_cycle_instance_id', 'perf_cycle_part_instance_fk')
                ->references('id')
                ->on('performance_cycle_instances')
                ->onDelete('cascade');
            $table->foreign('employee_id', 'perf_cycle_part_employee_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');
            $table->foreign('manager_id', 'perf_cycle_part_manager_fk')
                ->references('id')
                ->on('employees')
                ->onDelete('set null');
            $table->boolean('is_excluded')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate employee assignments
            $table->unique(['performance_cycle_instance_id', 'employee_id'], 'perf_cycle_participant_unique');

            // Indexes for common queries
            $table->index('is_excluded');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_cycle_participants');
    }
};
