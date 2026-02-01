<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the development_plan_check_ins table for recording manager/employee discussions.
     */
    public function up(): void
    {
        Schema::create('development_plan_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_plan_id');
            $table->date('check_in_date');
            $table->text('notes');
            // Note: created_by does NOT have FK constraint because users table is in platform database
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('development_plan_id', 'development_plan_check_ins_plan_fk')
                ->references('id')
                ->on('development_plans')
                ->onDelete('cascade');

            // Indexes
            $table->index('created_by');
            $table->index('check_in_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_plan_check_ins');
    }
};
