<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the document_versions table for tracking document version history.
     * Each version has a unique version number per document.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();

            // Document reference (required)
            $table->foreignId('document_id')
                ->constrained('documents')
                ->onDelete('cascade');

            // Version information
            $table->unsignedInteger('version_number');
            $table->string('stored_filename', 255);
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->text('version_notes')->nullable();

            // Audit field - who uploaded this version
            // Note: No foreign key constraint as users are in platform database
            $table->unsignedBigInteger('uploaded_by');

            $table->timestamps();

            // Index on uploaded_by for query performance
            $table->index('uploaded_by', 'doc_version_uploaded_by_idx');

            // Unique constraint on (document_id, version_number)
            $table->unique(['document_id', 'version_number'], 'doc_version_unique');

            // Index on document_id for version history queries
            $table->index('document_id', 'doc_version_document_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
