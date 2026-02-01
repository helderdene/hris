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
        Schema::create('onboarding_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onboarding_template_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('assigned_role')->index();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->json('equipment_details')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_checklist_items');
    }
};
