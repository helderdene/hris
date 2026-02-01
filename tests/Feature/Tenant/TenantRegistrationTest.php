<?php

/**
 * Tests for the Tenant Registration Flow
 *
 * These tests verify the complete tenant registration process including
 * tenant creation, schema provisioning, admin user assignment, and validation.
 */

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

it('creates tenant with valid slug during registration', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'Acme Corporation',
        'slug' => 'acme-corp',
        'business_info' => [
            'company_name' => 'Acme Corporation Inc.',
            'address' => '123 Main Street, Manila',
            'tin' => '123-456-789-000',
        ],
    ]);

    $response->assertRedirect();

    $tenant = Tenant::where('slug', 'acme-corp')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Corporation')
        ->and($tenant->slug)->toBe('acme-corp')
        ->and($tenant->business_info['company_name'])->toBe('Acme Corporation Inc.');
});

it('provisions schema automatically on registration', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Mock the TenantDatabaseManager to verify it gets called
    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('createSchema')->once();
    $mockManager->shouldReceive('migrateSchema')->once();

    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'Test Organization',
        'slug' => 'test-org',
        'business_info' => [
            'company_name' => 'Test Organization Inc.',
        ],
    ]);
});

it('assigns first user as tenant admin', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Mock the TenantDatabaseManager
    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('createSchema');
    $mockManager->shouldReceive('migrateSchema');
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'New Company',
        'slug' => 'new-company',
        'business_info' => [
            'company_name' => 'New Company LLC',
        ],
    ]);

    $tenant = Tenant::where('slug', 'new-company')->first();
    $tenantUser = $tenant->users()->where('user_id', $user->id)->first();

    expect($tenantUser)->not->toBeNull()
        ->and($tenantUser->pivot->role)->toBe(TenantUserRole::Admin);
});

it('validates slug uniqueness', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Create an existing tenant with the same slug
    Tenant::factory()->create(['slug' => 'existing-company']);

    $response = $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'Another Company',
        'slug' => 'existing-company',
        'business_info' => [
            'company_name' => 'Another Company Inc.',
        ],
    ]);

    $response->assertSessionHasErrors('slug');
});

it('redirects to subdomain after successful registration', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Mock the TenantDatabaseManager
    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('createSchema');
    $mockManager->shouldReceive('migrateSchema');
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    $response = $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'Redirect Test Corp',
        'slug' => 'redirect-test',
        'business_info' => [
            'company_name' => 'Redirect Test Corp Inc.',
        ],
    ]);

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('redirect-test.kasamahr.test')
        ->and($location)->toContain('token=');

    // Verify redirect token was created
    $tenant = Tenant::where('slug', 'redirect-test')->first();
    $token = TenantRedirectToken::where('tenant_id', $tenant->id)->first();
    expect($token)->not->toBeNull()
        ->and($token->user_id)->toBe($user->id);
});

it('validates slug URL-safe format', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Test invalid slugs
    $invalidSlugs = [
        'UPPERCASE' => 'Uppercase not allowed',
        'spaces here' => 'Spaces not allowed',
        'special@chars' => 'Special characters not allowed',
        '-starts-with-dash' => 'Cannot start with dash',
        'ends-with-dash-' => 'Cannot end with dash',
    ];

    foreach ($invalidSlugs as $slug => $description) {
        $response = $this->actingAs($user)->post(route('tenant.register.store'), [
            'name' => 'Test Company',
            'slug' => $slug,
            'business_info' => [
                'company_name' => 'Test Company Inc.',
            ],
        ]);

        $response->assertSessionHasErrors('slug');
    }
});
