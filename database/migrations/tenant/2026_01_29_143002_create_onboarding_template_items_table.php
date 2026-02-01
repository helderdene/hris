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
        Schema::create('onboarding_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_template_id')->constrained()->cascadeOnDelete();
            $table->string('category')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('assigned_role')->index();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('due_days_offset')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_template_items');
    }
};
