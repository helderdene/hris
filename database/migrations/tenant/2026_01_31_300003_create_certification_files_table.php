<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the certification_files table for storing uploaded certificate
     * documents. Each certification can have multiple supporting files.
     */
    public function up(): void
    {
        Schema::create('certification_files', function (Blueprint $table) {
            $table->id();

            // Certification reference
            $table->foreignId('certification_id')
                ->constrained('certifications')
                ->onDelete('cascade');

            // File information
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');

            // Audit field
            $table->unsignedBigInteger('uploaded_by');

            $table->timestamps();

            // Indexes
            $table->index('certification_id');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_files');
    }
};
