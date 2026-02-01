<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the departments table with hierarchical structure support.
     * Uses self-referential parent_id for department hierarchy.
     * Soft deletes preserve referential integrity.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            // References employee in employees table - no FK constraint
            // since employees may not exist yet
            $table->unsignedBigInteger('department_head_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on('departments')
                ->onDelete('restrict');

            $table->index('parent_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
