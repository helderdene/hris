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
        Schema::create('employee_offboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('separation_type');
            $table->date('filed_date')->nullable();
            $table->date('last_working_date');
            $table->text('reason')->nullable();
            $table->timestamp('separated_at')->nullable();
            $table->date('exit_interview_date')->nullable();
            $table->string('exit_interviewer')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->string('final_pay_status')->default('pending');
            $table->decimal('final_pay_amount', 12, 2)->nullable();
            $table->date('final_pay_released_date')->nullable();
            $table->string('coe_status')->default('pending');
            $table->date('coe_issued_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('offboarding_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_offboarding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('remarks', 500)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedBigInteger('cleared_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offboarding_clearance_items');
        Schema::dropIfExists('employee_offboardings');
    }
};
