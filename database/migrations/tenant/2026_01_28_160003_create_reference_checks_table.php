<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('referee_name');
            $table->string('referee_email')->nullable();
            $table->string('referee_phone')->nullable();
            $table->string('referee_company')->nullable();
            $table->string('relationship')->nullable();
            $table->boolean('contacted')->default(false);
            $table->dateTime('contacted_at')->nullable();
            $table->text('feedback')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_checks');
    }
};
