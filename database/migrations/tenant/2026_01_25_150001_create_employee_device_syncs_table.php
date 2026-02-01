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
        Schema::create('employee_device_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_device_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('last_message_id')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'biometric_device_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_device_syncs');
    }
};
