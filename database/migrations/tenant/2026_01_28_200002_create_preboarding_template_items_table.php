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
        Schema::create('preboarding_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preboarding_template_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('document_category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preboarding_template_items');
    }
};
