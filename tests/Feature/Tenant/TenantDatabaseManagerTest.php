<?php

use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates schema with correct naming convention', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme-corp']);
    $manager = app(TenantDatabaseManager::class);

    $expectedDatabaseName = 'kasamahr_tenant_acme-corp';

    // For SQLite testing, we verify the tenant database file path is correct
    if (config('database.default') === 'sqlite') {
        $manager->createSchema($tenant);

        $tenantDbPath = database_path('tenant_acme-corp.sqlite');
        expect(file_exists($tenantDbPath))->toBeTrue();

        // Clean up
        @unlink($tenantDbPath);
    } else {
        // For MySQL, we verify the database name generation
        expect($tenant->getDatabaseName())->toBe($expectedDatabaseName);
    }
});

it('switches connection via Config and DB facade', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
    $manager = app(TenantDatabaseManager::class);

    // For SQLite, create the tenant database first
    if (config('database.default') === 'sqlite') {
        $manager->createSchema($tenant);
    }

    $manager->switchConnection($tenant);

    // Verify the config was updated correctly
    if (config('database.default') === 'sqlite') {
        $expectedPath = database_path('tenant_test-tenant.sqlite');
        expect(config('database.connections.tenant.database'))->toBe($expectedPath);

        // Clean up
        @unlink($expectedPath);
    } else {
        expect(config('database.connections.tenant.database'))->toBe('kasamahr_tenant_test-tenant');
    }
});

it('runs migrations on tenant schema', function () {
    $tenant = Tenant::factory()->create(['slug' => 'migrated-tenant']);
    $manager = app(TenantDatabaseManager::class);

    // Create the schema first
    $manager->createSchema($tenant);

    // Switch to tenant connection
    $manager->switchConnection($tenant);

    // Run migrations
    $manager->migrateSchema($tenant);

    // Verify migrations table exists (created by Laravel during migration)
    $migrationsExist = Schema::connection('tenant')->hasTable('migrations');
    expect($migrationsExist)->toBeTrue();

    // Clean up for SQLite
    if (config('database.default') === 'sqlite') {
        $tenantDbPath = database_path('tenant_migrated-tenant.sqlite');
        @unlink($tenantDbPath);
    }
});

it('checks if schema exists correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'existing-tenant']);
    $nonExistentTenant = Tenant::factory()->create(['slug' => 'non-existent']);
    $manager = app(TenantDatabaseManager::class);

    // Schema should not exist initially
    expect($manager->schemaExists($nonExistentTenant))->toBeFalse();

    // Create the schema
    $manager->createSchema($tenant);

    // Now it should exist
    expect($manager->schemaExists($tenant))->toBeTrue();

    // Clean up for SQLite
    if (config('database.default') === 'sqlite') {
        $tenantDbPath = database_path('tenant_existing-tenant.sqlite');
        @unlink($tenantDbPath);
    }
});
