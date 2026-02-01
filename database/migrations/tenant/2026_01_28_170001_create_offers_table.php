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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_template_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content');
            $table->string('status')->default('draft')->index();
            $table->decimal('salary', 12, 2);
            $table->string('salary_currency', 3)->default('PHP');
            $table->string('salary_frequency')->default('monthly');
            $table->json('benefits')->nullable();
            $table->text('terms')->nullable();
            $table->date('start_date');
            $table->date('expiry_date')->nullable();
            $table->string('position_title');
            $table->string('department')->nullable();
            $table->string('work_location')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('pdf_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
