<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is a placeholder migration for the tenant schema.
     * Future employee-related tables and other tenant-specific
     * data structures will be added here.
     *
     * Note: The user_id column does NOT have a foreign key constraint
     * because the users table lives in the platform schema, not the
     * tenant schema. Application-level validation should ensure
     * referential integrity.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // User ID references platform schema users table - no FK constraint
            // since it's a cross-database reference
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->string('status')->default('active');
            $table->json('address')->nullable();
            $table->json('emergency_contact')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index(['status', 'department']);
            $table->index('hire_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
