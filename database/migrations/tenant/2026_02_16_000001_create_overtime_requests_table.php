<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('daily_time_record_id')->nullable()->constrained('daily_time_records')->nullOnDelete();
            $table->string('reference_number')->unique();
            $table->date('overtime_date');
            $table->time('expected_start_time')->nullable();
            $table->time('expected_end_time')->nullable();
            $table->unsignedInteger('expected_minutes');
            $table->string('overtime_type');
            $table->text('reason');
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_approval_level')->default(0);
            $table->unsignedTinyInteger('total_approval_levels')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'overtime_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
