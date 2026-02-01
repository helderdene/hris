<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the courses table for training course catalog.
     * Stores course details including delivery method, provider info, and content.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('delivery_method', 50);
            $table->string('provider_type', 50);
            $table->string('provider_name')->nullable();
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->integer('duration_days')->nullable();
            $table->string('status', 50)->default('draft');
            $table->string('level', 50)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->integer('max_participants')->nullable();
            $table->json('learning_objectives')->nullable();
            $table->text('syllabus')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('delivery_method');
            $table->index('provider_type');
            $table->index('level');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
