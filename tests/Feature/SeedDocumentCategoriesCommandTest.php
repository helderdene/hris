<?php

use App\Models\DocumentCategory;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('seeds document categories for a specific tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-company']);

    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('switchConnection')->once()->with(
        Mockery::on(fn ($t) => $t->id === $tenant->id)
    );
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $this->artisan('tenant:seed-document-categories', ['tenant' => 'test-company'])
        ->expectsOutputToContain('test-company')
        ->expectsOutputToContain('Done!')
        ->assertSuccessful();

    expect(DocumentCategory::count())->toBe(5);
});

it('seeds document categories for all tenants', function () {
    Tenant::factory()->create(['slug' => 'company-one']);
    Tenant::factory()->create(['slug' => 'company-two']);

    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('switchConnection')->twice();
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $this->artisan('tenant:seed-document-categories')
        ->expectsOutputToContain('2 tenant(s)')
        ->expectsOutputToContain('Done!')
        ->assertSuccessful();
});

it('fails when tenant slug does not exist', function () {
    $this->artisan('tenant:seed-document-categories', ['tenant' => 'nonexistent'])
        ->expectsOutputToContain("Tenant with slug 'nonexistent' not found.")
        ->assertFailed();
});

it('fails when no tenants exist', function () {
    $this->artisan('tenant:seed-document-categories')
        ->expectsOutputToContain('No tenants found.')
        ->assertFailed();
});

it('does not create duplicate categories when run multiple times', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-company']);

    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('switchConnection');
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $this->artisan('tenant:seed-document-categories', ['tenant' => 'test-company'])
        ->assertSuccessful();

    $this->artisan('tenant:seed-document-categories', ['tenant' => 'test-company'])
        ->assertSuccessful();

    expect(DocumentCategory::count())->toBe(5);
});
