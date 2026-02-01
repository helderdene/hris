<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the compliance_assignment_rules table for auto-assignment logic.
     * Defines rules for automatically assigning compliance training to employees.
     */
    public function up(): void
    {
        Schema::create('compliance_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_course_id')->constrained('compliance_courses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rule_type', 50);
            $table->json('conditions');
            $table->integer('days_to_complete_override')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('apply_to_new_hires')->default(true);
            $table->boolean('apply_to_existing')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('compliance_course_id');
            $table->index('rule_type');
            $table->index('is_active');
            $table->index('priority');
            $table->index('effective_from');
            $table->index('effective_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_assignment_rules');
    }
};
