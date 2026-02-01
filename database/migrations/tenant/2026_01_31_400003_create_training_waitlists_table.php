<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the training_waitlists table for managing session waitlists.
     * Supports FIFO ordering with position column.
     */
    public function up(): void
    {
        Schema::create('training_waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status', 50)->default('waiting');
            $table->unsignedInteger('position')->comment('Queue order for FIFO processing');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
            $table->index('status');
            $table->index('position');
            $table->index('joined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_waitlists');
    }
};
