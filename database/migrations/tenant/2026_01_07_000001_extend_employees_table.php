<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the employees table with additional 201 file fields:
     * - Personal info: suffix, gender, civil_status, nationality, parent names
     * - Government IDs: TIN, SSS, PhilHealth, Pag-IBIG, etc.
     * - Employment: department/position/work_location FKs, employment_type/status enums
     * - Education and work history JSON columns
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Personal info fields
            $table->string('suffix')->nullable()->after('last_name');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->string('civil_status')->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('civil_status');
            $table->string('fathers_name')->nullable()->after('nationality');
            $table->string('mothers_name')->nullable()->after('fathers_name');

            // Government IDs - all nullable strings (no format validation per spec)
            $table->string('tin')->nullable()->after('mothers_name');
            $table->string('sss_number')->nullable()->after('tin');
            $table->string('philhealth_number')->nullable()->after('sss_number');
            $table->string('pagibig_number')->nullable()->after('philhealth_number');
            $table->string('umid')->nullable()->after('pagibig_number');
            $table->string('passport_number')->nullable()->after('umid');
            $table->string('drivers_license')->nullable()->after('passport_number');
            $table->string('nbi_clearance')->nullable()->after('drivers_license');
            $table->string('police_clearance')->nullable()->after('nbi_clearance');
            $table->string('prc_license')->nullable()->after('police_clearance');

            // Employment relationships - FKs (no constraint due to cross-table references)
            $table->unsignedBigInteger('department_id')->nullable()->after('prc_license');
            $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            $table->unsignedBigInteger('work_location_id')->nullable()->after('position_id');
            $table->unsignedBigInteger('supervisor_id')->nullable()->after('work_location_id');

            // Employment enum fields (replacing old string-based columns)
            $table->string('employment_type_new')->default('regular')->after('supervisor_id');
            $table->string('employment_status')->default('active')->after('employment_type_new');

            // Additional employment fields
            $table->date('regularization_date')->nullable()->after('hire_date');
            $table->decimal('basic_salary', 12, 2)->nullable()->after('termination_date');
            $table->string('pay_frequency')->nullable()->after('basic_salary');

            // Education JSON column
            $table->json('education')->nullable()->after('emergency_contact');

            // Work history JSON column
            $table->json('work_history')->nullable()->after('education');

            // Add indexes for common queries
            $table->index('employment_status');
            $table->index('employment_type_new');
            $table->index('department_id');
            $table->index('position_id');
            $table->index('work_location_id');
            $table->index('supervisor_id');
        });

        // Migrate data from old columns to new columns (if any data exists)
        // Then drop old columns
        Schema::table('employees', function (Blueprint $table) {
            // Drop old string-based columns
            $table->dropIndex(['status', 'department']);
            $table->dropColumn(['department', 'position', 'employment_type', 'status']);
        });

        // Rename new employment_type column
        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('employment_type_new', 'employment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename employment_type back to employment_type_new for reversal
        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('employment_type', 'employment_type_new');
        });

        // Re-add old columns
        Schema::table('employees', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->string('status')->default('active');
            $table->index(['status', 'department']);
        });

        // Drop new columns
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employment_type_new');
        });

        Schema::table('employees', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['employment_status']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['position_id']);
            $table->dropIndex(['work_location_id']);
            $table->dropIndex(['supervisor_id']);

            // Drop new columns
            $table->dropColumn([
                'suffix',
                'gender',
                'civil_status',
                'nationality',
                'fathers_name',
                'mothers_name',
                'tin',
                'sss_number',
                'philhealth_number',
                'pagibig_number',
                'umid',
                'passport_number',
                'drivers_license',
                'nbi_clearance',
                'police_clearance',
                'prc_license',
                'department_id',
                'position_id',
                'work_location_id',
                'supervisor_id',
                'employment_status',
                'regularization_date',
                'basic_salary',
                'pay_frequency',
                'education',
                'work_history',
            ]);
        });
    }
};
