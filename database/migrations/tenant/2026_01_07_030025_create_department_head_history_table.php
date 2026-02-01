<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the department_head_history table to track department head changes over time.
     * Only one active head at a time (ended_at is null for current head).
     */
    public function up(): void
    {
        Schema::create('department_head_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->onDelete('cascade');
            // References employee in employees table - nullable until employees exist
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index('department_id');
            $table->index('employee_id');
            $table->index('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_head_history');
    }
};
