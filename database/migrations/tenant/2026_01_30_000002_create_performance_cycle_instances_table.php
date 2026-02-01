<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the performance_cycle_instances table for storing specific evaluation period instances.
     * Each instance represents an actual performance evaluation period with date boundaries and status tracking.
     */
    public function up(): void
    {
        Schema::create('performance_cycle_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_id')
                ->constrained('performance_cycles')
                ->onDelete('cascade');
            $table->string('name');
            $table->integer('year');
            $table->integer('instance_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');
            $table->integer('employee_count')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('evaluation_started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            // closed_by references platform schema users table - no FK constraint
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint to prevent duplicate instances
            $table->unique(['performance_cycle_id', 'year', 'instance_number'], 'perf_cycle_instance_unique');

            // Indexes for common queries
            $table->index('year');
            $table->index('status');
            $table->index(['year', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_cycle_instances');
    }
};
