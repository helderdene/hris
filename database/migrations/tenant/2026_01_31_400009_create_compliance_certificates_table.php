<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_certificates table for completion certificates.
     * Stores certificate details, validity periods, and file references.
     */
    public function up(): void
    {
        Schema::create('compliance_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_assignment_id')->constrained('compliance_assignments')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->date('issued_date');
            $table->date('valid_until')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->text('revocation_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index('compliance_assignment_id');
            $table->index('certificate_number');
            $table->index('issued_date');
            $table->index('valid_until');
            $table->index('is_revoked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_certificates');
    }
};
