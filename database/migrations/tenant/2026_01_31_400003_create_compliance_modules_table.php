<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_modules table for compliance course content.
     * Stores module content including video, text, PDF, SCORM, and assessment types.
     */
    public function up(): void
    {
        Schema::create('compliance_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_course_id')->constrained('compliance_courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('content_type', 50);
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('external_url')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->decimal('passing_score', 5, 2)->nullable();
            $table->integer('max_attempts')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('compliance_course_id');
            $table->index('content_type');
            $table->index('sort_order');
            $table->index('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_modules');
    }
};
