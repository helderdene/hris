<?php

/**
 * Tests for the Tenant Selection UI
 *
 * These tests verify the tenant selection page renders correctly,
 * handles user interactions, and properly displays tenant information.
 */

use App\Http\Controllers\TenantSelectorController;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

it('renders tenant selection page with user available tenants', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $tenant1 = Tenant::factory()->create([
        'name' => 'Acme Corporation',
        'slug' => 'acme',
        'primary_color' => '#3b82f6',
        'logo_path' => null,
    ]);
    $tenant2 = Tenant::factory()->create([
        'name' => 'Globex Industries',
        'slug' => 'globex',
        'primary_color' => '#10b981',
        'logo_path' => '/storage/tenants/globex/logo.png',
    ]);

    $user->tenants()->attach($tenant1, ['role' => 'admin']);
    $user->tenants()->attach($tenant2, ['role' => 'employee']);

    // Test the controller directly to avoid Vite manifest issues
    $request = Request::create('/select-tenant', 'GET');
    $request->setUserResolver(fn () => $user);

    $controller = new TenantSelectorController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('TenantSelector');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $tenants = $props['tenants'];

    expect($tenants)->toHaveCount(2);
    expect($tenants[0]['name'])->toBe('Acme Corporation');
    expect($tenants[0]['slug'])->toBe('acme');
    expect($tenants[0]['primary_color'])->toBe('#3b82f6');
    expect($tenants[0]['role'])->toBe('admin');
    expect($tenants[0]['role_label'])->toBe('Admin');
    expect($tenants[1]['name'])->toBe('Globex Industries');
    expect($tenants[1]['slug'])->toBe('globex');
    expect($tenants[1]['primary_color'])->toBe('#10b981');
    expect($tenants[1]['logo_path'])->toBe('/storage/tenants/globex/logo.png');
    expect($tenants[1]['role'])->toBe('employee');
    expect($tenants[1]['role_label'])->toBe('Employee');
});

it('initiates redirect flow when tenant card is clicked', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Corporation',
        'slug' => 'acme',
    ]);

    $user->tenants()->attach($tenant, ['role' => 'admin']);

    $response = $this->actingAs($user)->post(route('tenant.select.submit', $tenant));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('acme.kasamahr.test')
        ->and($location)->toContain('token=');

    // Verify token was created
    $token = TenantRedirectToken::first();
    expect($token)->not->toBeNull()
        ->and($token->user_id)->toBe($user->id)
        ->and($token->tenant_id)->toBe($tenant->id);
});

it('shows empty state when user has no tenants', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Test the controller directly to avoid Vite manifest issues
    $request = Request::create('/select-tenant', 'GET');
    $request->setUserResolver(fn () => $user);

    $controller = new TenantSelectorController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('TenantSelector');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $tenants = $props['tenants'];

    expect($tenants)->toHaveCount(0);
});

it('displays tenants ordered by name', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $tenantZ = Tenant::factory()->create(['name' => 'Zebra Corp', 'slug' => 'zebra']);
    $tenantA = Tenant::factory()->create(['name' => 'Alpha Inc', 'slug' => 'alpha']);
    $tenantM = Tenant::factory()->create(['name' => 'Metro Ltd', 'slug' => 'metro']);

    $user->tenants()->attach($tenantZ, ['role' => 'employee']);
    $user->tenants()->attach($tenantA, ['role' => 'admin']);
    $user->tenants()->attach($tenantM, ['role' => 'employee']);

    // Test the controller directly to avoid Vite manifest issues
    $request = Request::create('/select-tenant', 'GET');
    $request->setUserResolver(fn () => $user);

    $controller = new TenantSelectorController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties
    $reflection = new ReflectionClass($inertiaResponse);

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $tenants = $props['tenants'];

    expect($tenants)->toHaveCount(3);
    expect($tenants[0]['name'])->toBe('Alpha Inc');
    expect($tenants[1]['name'])->toBe('Metro Ltd');
    expect($tenants[2]['name'])->toBe('Zebra Corp');
});
