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
        Schema::create('evaluation_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_cycle_participant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('reviewer_employee_id')
                ->constrained('employees');
            $table->string('reviewer_type', 20); // self, manager, peer, direct_report
            $table->string('status', 20)->default('pending'); // pending, in_progress, submitted, declined
            $table->string('assignment_method', 20); // automatic, manager_selected, hr_assigned
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['performance_cycle_participant_id', 'reviewer_employee_id', 'reviewer_type'],
                'unique_reviewer'
            );
            $table->index('reviewer_employee_id');
            $table->index('status');
            $table->index('reviewer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_reviewers');
    }
};
