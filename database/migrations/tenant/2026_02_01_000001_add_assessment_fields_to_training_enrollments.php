<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds assessment and certificate fields to the training_enrollments table
     * for tracking training completion and certification.
     */
    public function up(): void
    {
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->decimal('assessment_score', 5, 2)->nullable()->after('attended_at');
            $table->string('completion_status')->nullable()->after('assessment_score');
            $table->string('certificate_number')->nullable()->after('completion_status');
            $table->date('certificate_issued_at')->nullable()->after('certificate_number');

            $table->index('completion_status');
            $table->index('certificate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->dropIndex(['completion_status']);
            $table->dropIndex(['certificate_number']);

            $table->dropColumn([
                'assessment_score',
                'completion_status',
                'certificate_number',
                'certificate_issued_at',
            ]);
        });
    }
};
