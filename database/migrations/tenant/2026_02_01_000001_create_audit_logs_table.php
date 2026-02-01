<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the audit_logs table for tracking all model changes.
     * Stores before/after values as JSON for flexibility.
     *
     * Note: user_id references platform schema users table - no FK constraint
     * since it's a cross-database reference.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            // Index for finding logs by model
            $table->index(['auditable_type', 'auditable_id']);

            // Index for finding logs by user
            $table->index('user_id');

            // Index for filtering by action type
            $table->index('action');

            // Index for date range queries
            $table->index('created_at');

            // Composite index for filtering by model type and date
            $table->index(['auditable_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
