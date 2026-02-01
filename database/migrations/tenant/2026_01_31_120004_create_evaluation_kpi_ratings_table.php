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
        Schema::create('evaluation_kpi_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_response_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('kpi_assignment_id')
                ->constrained();
            $table->tinyInteger('rating')->unsigned()->nullable(); // 1-5
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_response_id', 'kpi_assignment_id'],
                'unique_kpi_rating'
            );
            $table->index('kpi_assignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_kpi_ratings');
    }
};
