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
        Schema::create('time_record_punches', function (Blueprint $table) {
            $table->id();

            // DTR reference
            $table->foreignId('daily_time_record_id')->constrained()->cascadeOnDelete();

            // Original attendance log reference
            $table->foreignId('attendance_log_id')->constrained()->cascadeOnDelete();

            // Punch type: in, out
            $table->string('punch_type');

            // Actual punch time (denormalized for easier querying)
            $table->datetime('punched_at');

            // Validation flag
            $table->boolean('is_valid')->default(true);
            $table->string('invalidation_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('daily_time_record_id');
            $table->index('attendance_log_id');
            $table->index('punch_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_record_punches');
    }
};
