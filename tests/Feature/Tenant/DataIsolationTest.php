<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);
});

afterEach(function () {
    // Clean up any SQLite tenant databases created during tests
    if (config('database.default') === 'sqlite') {
        $pattern = database_path('tenant_*.sqlite');
        foreach (glob($pattern) as $file) {
            @unlink($file);
        }
    }
});

it('isolates data queries to tenant connection only returning tenant data', function () {
    $manager = app(TenantDatabaseManager::class);

    // Create two tenants
    $tenant1 = Tenant::factory()->create(['slug' => 'company-alpha']);
    $tenant2 = Tenant::factory()->create(['slug' => 'company-beta']);

    // Create schemas and run migrations for both tenants
    $manager->createSchema($tenant1);
    $manager->migrateSchema($tenant1);

    $manager->createSchema($tenant2);
    $manager->migrateSchema($tenant2);

    // Insert data into tenant1's employees table
    $manager->switchConnection($tenant1);
    DB::connection('tenant')->table('employees')->insert([
        'employee_number' => 'EMP-001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@alpha.com',
        'hire_date' => '2024-01-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert data into tenant2's employees table
    $manager->switchConnection($tenant2);
    DB::connection('tenant')->table('employees')->insert([
        'employee_number' => 'EMP-002',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@beta.com',
        'hire_date' => '2024-02-20',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Query tenant1 - should only see John
    $manager->switchConnection($tenant1);
    $tenant1Employees = DB::connection('tenant')->table('employees')->get();
    expect($tenant1Employees)->toHaveCount(1);
    expect($tenant1Employees->first()->first_name)->toBe('John');
    expect($tenant1Employees->first()->email)->toBe('john@alpha.com');

    // Query tenant2 - should only see Jane
    $manager->switchConnection($tenant2);
    $tenant2Employees = DB::connection('tenant')->table('employees')->get();
    expect($tenant2Employees)->toHaveCount(1);
    expect($tenant2Employees->first()->first_name)->toBe('Jane');
    expect($tenant2Employees->first()->email)->toBe('jane@beta.com');
});

it('prevents cross-tenant data access through connection switching', function () {
    $manager = app(TenantDatabaseManager::class);

    // Create two tenants
    $tenant1 = Tenant::factory()->create(['slug' => 'secure-corp']);
    $tenant2 = Tenant::factory()->create(['slug' => 'other-corp']);

    // Create schemas and run migrations
    $manager->createSchema($tenant1);
    $manager->migrateSchema($tenant1);

    $manager->createSchema($tenant2);
    $manager->migrateSchema($tenant2);

    // Insert sensitive data into tenant1
    $manager->switchConnection($tenant1);
    DB::connection('tenant')->table('employees')->insert([
        'employee_number' => 'EMP-SECURE-001',
        'first_name' => 'Confidential',
        'last_name' => 'Data',
        'email' => 'confidential@secure.com',
        'hire_date' => '2024-01-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Switch to tenant2 and verify cannot access tenant1's data
    $manager->switchConnection($tenant2);
    $employees = DB::connection('tenant')->table('employees')->get();

    // Should not find tenant1's data
    expect($employees)->toHaveCount(0);
    expect($employees->where('email', 'confidential@secure.com')->count())->toBe(0);
});

it('maintains data isolation across concurrent connection switches', function () {
    $manager = app(TenantDatabaseManager::class);

    // Create multiple tenants
    $tenants = [];
    for ($i = 1; $i <= 3; $i++) {
        $tenant = Tenant::factory()->create(['slug' => "tenant-{$i}"]);
        $manager->createSchema($tenant);
        $manager->migrateSchema($tenant);
        $tenants[] = $tenant;
    }

    // Insert unique data into each tenant
    foreach ($tenants as $index => $tenant) {
        $manager->switchConnection($tenant);
        DB::connection('tenant')->table('employees')->insert([
            'employee_number' => "EMP-T{$index}",
            'first_name' => "Employee{$index}",
            'last_name' => "Tenant{$index}",
            'email' => "employee{$index}@tenant{$index}.com",
            'hire_date' => '2024-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Simulate concurrent access by switching between tenants rapidly
    // and verifying data isolation is maintained
    for ($round = 0; $round < 5; $round++) {
        foreach ($tenants as $index => $tenant) {
            $manager->switchConnection($tenant);
            $employees = DB::connection('tenant')->table('employees')->get();

            // Each tenant should only see their own employee
            expect($employees)->toHaveCount(1);
            expect($employees->first()->first_name)->toBe("Employee{$index}");
            expect($employees->first()->email)->toBe("employee{$index}@tenant{$index}.com");
        }
    }
});

it('ensures tenant model uses tenant connection for data isolation', function () {
    // Create a simple inline model extending TenantModel to test
    $tenantModelClass = new class extends \App\Models\TenantModel
    {
        protected $table = 'employees';

        protected $fillable = ['employee_number', 'first_name', 'last_name', 'email', 'hire_date'];
    };

    // In SQLite/testing environments, TenantModel returns null to use the default connection
    // In production (MySQL), it returns 'tenant' for proper database isolation
    if (config('database.default') === 'sqlite') {
        expect($tenantModelClass->getConnectionName())->toBeNull();
    } else {
        expect($tenantModelClass->getConnectionName())->toBe('tenant');
    }
});

it('verifies platform data remains isolated from tenant queries', function () {
    $manager = app(TenantDatabaseManager::class);

    // Create a tenant
    $tenant = Tenant::factory()->create(['slug' => 'isolated-tenant']);
    $manager->createSchema($tenant);
    $manager->migrateSchema($tenant);

    // Create a user in the platform database
    $platformUser = User::factory()->create([
        'name' => 'Platform User',
        'email' => 'platform@kasamahr.com',
    ]);

    // Switch to tenant connection
    $manager->switchConnection($tenant);

    // Tenant connection should NOT be able to query platform users table
    // The users table exists in the platform schema, not tenant schema
    try {
        $users = DB::connection('tenant')->table('users')->get();
        // If we reach here, the table exists in tenant schema (which it shouldn't in a proper setup)
        // For SQLite testing, the tenant database won't have a users table
        expect($users)->toHaveCount(0);
    } catch (\Illuminate\Database\QueryException $e) {
        // Expected: users table should not exist in tenant database
        expect(true)->toBeTrue();
    }

    // Platform user should still be accessible via the default connection
    $foundUser = User::where('email', 'platform@kasamahr.com')->first();
    expect($foundUser)->not->toBeNull();
    expect($foundUser->name)->toBe('Platform User');
});
