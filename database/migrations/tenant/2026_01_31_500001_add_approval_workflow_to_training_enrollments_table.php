<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds approval workflow fields to the training_enrollments table.
     */
    public function up(): void
    {
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->string('reference_number', 20)->unique()->nullable()->after('id');
            $table->timestamp('submitted_at')->nullable()->after('enrolled_at');
            $table->text('request_reason')->nullable()->after('notes');
            $table->foreignId('approver_employee_id')->nullable()->after('request_reason')
                ->constrained('employees')->nullOnDelete();
            $table->string('approver_name')->nullable()->after('approver_employee_id');
            $table->string('approver_position')->nullable()->after('approver_name');
            $table->text('approver_remarks')->nullable()->after('approver_position');
            $table->timestamp('approved_at')->nullable()->after('approver_remarks');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            $table->index('reference_number');
            $table->index('submitted_at');
            $table->index('approver_employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_enrollments', function (Blueprint $table) {
            $table->dropIndex(['reference_number']);
            $table->dropIndex(['submitted_at']);
            $table->dropIndex(['approver_employee_id']);

            $table->dropForeign(['approver_employee_id']);

            $table->dropColumn([
                'reference_number',
                'submitted_at',
                'request_reason',
                'approver_employee_id',
                'approver_name',
                'approver_position',
                'approver_remarks',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
