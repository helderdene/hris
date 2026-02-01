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
        Schema::create('bir_2316_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->year('tax_year');
            $table->json('compensation_data')->nullable()->comment('Full year compensation breakdown');
            $table->string('pdf_path')->nullable()->comment('Path to generated PDF certificate');
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->comment('User ID from platform database');
            $table->timestamps();

            $table->unique(['employee_id', 'tax_year']);
            $table->index('tax_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir_2316_certificates');
    }
};
