<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the kpi_templates table for storing reusable KPI definitions.
     * Templates define the base KPI structure that can be assigned to participants
     * with specific targets and weights.
     */
    public function up(): void
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('metric_unit', 50)->default('units');
            $table->decimal('default_target', 15, 2)->nullable();
            $table->decimal('default_weight', 5, 2)->default(1.00);
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('is_active');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_templates');
    }
};
