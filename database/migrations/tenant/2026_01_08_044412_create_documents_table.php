<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the documents table for storing employee and company documents.
     * Employee documents have an employee_id, company documents have employee_id as null.
     * Soft deletes preserve document history.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // Employee reference (nullable for company documents)
            $table->unsignedBigInteger('employee_id')->nullable();

            // Category reference (required)
            $table->foreignId('document_category_id')
                ->constrained('document_categories')
                ->onDelete('restrict');

            // Document metadata
            $table->string('name', 255);
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->boolean('is_company_document')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Foreign key for employee (cascade delete)
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onDelete('cascade');

            // Indexes for common queries
            $table->index('employee_id');
            $table->index('document_category_id');
            $table->index('is_company_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
