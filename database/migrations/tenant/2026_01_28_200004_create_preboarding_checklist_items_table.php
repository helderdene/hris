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
        Schema::create('preboarding_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preboarding_checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('preboarding_template_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('pending')->index();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('form_value')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preboarding_checklist_items');
    }
};
