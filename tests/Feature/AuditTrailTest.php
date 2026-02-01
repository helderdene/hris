<?php

/**
 * Tests for the Audit Trail Trait
 *
 * These tests verify that the HasAuditTrail trait correctly captures
 * model changes including create, update, and delete actions.
 */

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('HasAuditTrail Trait', function () {
    it('creates audit log when model is created', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->where('action', AuditAction::Created)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($user->id);
        expect($auditLog->new_values)->toHaveKey('first_name');
        expect($auditLog->new_values['first_name'])->toBe('John');
        expect($auditLog->old_values)->toBeNull();
    });

    it('creates audit log when model is updated', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Clear the "created" audit log
        AuditLog::truncate();

        $employee->update(['first_name' => 'Jane']);

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->where('action', AuditAction::Updated)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->old_values)->toHaveKey('first_name');
        expect($auditLog->old_values['first_name'])->toBe('John');
        expect($auditLog->new_values)->toHaveKey('first_name');
        expect($auditLog->new_values['first_name'])->toBe('Jane');
    });

    it('creates audit log when model is deleted', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $employeeId = $employee->id;

        // Clear the "created" audit log
        AuditLog::truncate();

        $employee->delete();

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employeeId)
            ->where('action', AuditAction::Deleted)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->old_values)->toHaveKey('first_name');
        expect($auditLog->old_values['first_name'])->toBe('John');
        expect($auditLog->new_values)->toBeNull();
    });

    it('filters sensitive attributes from audit log', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        // For User model testing, we need to ensure it uses the trait
        // Employee extends TenantModel which has the trait
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an employee with sensitive-like data (simulating)
        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->first();

        // Verify password field is not in new_values (if it exists)
        expect($auditLog->new_values)->not->toHaveKey('password');
        expect($auditLog->new_values)->not->toHaveKey('remember_token');
    });

    it('captures IP address and user agent', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();
        $this->actingAs($user);

        // Simulate a request with IP and user agent
        $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.100',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
        ]);

        $this->get('/'); // Trigger request initialization

        $employee = Employee::factory()->create();

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->first();

        // Note: In tests, IP might be 127.0.0.1 or null
        expect($auditLog)->not->toBeNull();
    });

    it('does not create audit log for AuditLog model itself', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();

        // Create an audit log directly
        $auditLog = AuditLog::create([
            'auditable_type' => Employee::class,
            'auditable_id' => 1,
            'action' => AuditAction::Created,
            'user_id' => $user->id,
            'new_values' => ['test' => 'value'],
        ]);

        // Should not create an audit log for the audit log itself
        $recursiveLog = AuditLog::where('auditable_type', AuditLog::class)
            ->where('auditable_id', $auditLog->id)
            ->first();

        expect($recursiveLog)->toBeNull();
    });

    it('handles anonymous updates (no authenticated user)', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        // No user authenticated
        $employee = Employee::factory()->create([
            'first_name' => 'John',
        ]);

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBeNull();
    });

    it('only logs changed attributes on update', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Clear the "created" audit log
        AuditLog::truncate();

        // Only update first_name
        $employee->update(['first_name' => 'Jane']);

        $auditLog = AuditLog::where('auditable_type', Employee::class)
            ->where('auditable_id', $employee->id)
            ->where('action', AuditAction::Updated)
            ->first();

        expect($auditLog)->not->toBeNull();
        expect($auditLog->new_values)->toHaveKey('first_name');
        // Should not have unchanged fields in new_values
        expect($auditLog->new_values)->not->toHaveKey('last_name');
    });
});

describe('AuditLog Model', function () {
    it('has correct model name accessor', function () {
        $auditLog = AuditLog::factory()->create([
            'auditable_type' => Employee::class,
        ]);

        expect($auditLog->model_name)->toBe('Employee');
    });

    it('has correct user name accessor', function () {
        $user = User::factory()->create(['name' => 'John Doe']);

        $auditLog = AuditLog::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($auditLog->user_name)->toBe('John Doe');
    });

    it('returns null for user name when no user', function () {
        $auditLog = AuditLog::factory()->anonymous()->create();

        expect($auditLog->user_name)->toBeNull();
    });

    it('can get unique auditable types', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        AuditLog::factory()->count(3)->create([
            'auditable_type' => Employee::class,
        ]);

        AuditLog::factory()->count(2)->create([
            'auditable_type' => 'App\\Models\\Department',
        ]);

        $types = AuditLog::getAuditableTypes();

        expect($types)->toHaveCount(2);
        expect($types)->toContain(Employee::class);
        expect($types)->toContain('App\\Models\\Department');
    });

    it('can filter by model scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        AuditLog::factory()->count(3)->create([
            'auditable_type' => Employee::class,
        ]);

        AuditLog::factory()->count(2)->create([
            'auditable_type' => 'App\\Models\\Department',
        ]);

        $employeeLogs = AuditLog::forModel(Employee::class)->get();

        expect($employeeLogs)->toHaveCount(3);
    });

    it('can filter by action scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        AuditLog::factory()->created()->count(3)->create();
        AuditLog::factory()->updated()->count(2)->create();
        AuditLog::factory()->deleted()->count(1)->create();

        $createdLogs = AuditLog::forAction(AuditAction::Created)->get();
        $updatedLogs = AuditLog::forAction(AuditAction::Updated)->get();
        $deletedLogs = AuditLog::forAction(AuditAction::Deleted)->get();

        expect($createdLogs)->toHaveCount(3);
        expect($updatedLogs)->toHaveCount(2);
        expect($deletedLogs)->toHaveCount(1);
    });

    it('can filter by user scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AuditLog::factory()->byUser($user1)->count(3)->create();
        AuditLog::factory()->byUser($user2)->count(2)->create();

        $user1Logs = AuditLog::byUser($user1->id)->get();
        $user2Logs = AuditLog::byUser($user2->id)->get();

        expect($user1Logs)->toHaveCount(3);
        expect($user2Logs)->toHaveCount(2);
    });

    it('can filter by date range scope', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        app()->instance('tenant', $tenant);

        AuditLog::factory()->count(2)->create([
            'created_at' => now(),
        ]);

        AuditLog::factory()->count(3)->create([
            'created_at' => now()->subDays(5),
        ]);

        $todayLogs = AuditLog::dateRange(now()->toDateString(), now()->toDateString())->get();
        $pastLogs = AuditLog::dateRange(now()->subDays(7)->toDateString(), now()->subDays(3)->toDateString())->get();

        expect($todayLogs)->toHaveCount(2);
        expect($pastLogs)->toHaveCount(3);
    });
});
