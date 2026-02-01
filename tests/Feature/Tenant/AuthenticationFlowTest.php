<?php

use App\Http\Controllers\TenantSelectorController;
use App\Http\Middleware\AuthenticateFromToken;
use App\Http\Responses\LoginResponse;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);
});

it('redirects to tenant selection when user has multiple tenants', function () {
    // Create a user with multiple tenants
    $user = User::factory()->withoutTwoFactor()->create();

    $tenant1 = Tenant::factory()->create(['slug' => 'acme']);
    $tenant2 = Tenant::factory()->create(['slug' => 'globex']);

    $user->tenants()->attach($tenant1, ['role' => 'admin']);
    $user->tenants()->attach($tenant2, ['role' => 'employee']);

    // Test the LoginResponse directly
    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $loginResponse = new LoginResponse;
    $response = $loginResponse->toResponse($request);

    // Should redirect to tenant selection page
    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toContain('/select-tenant');
});

it('redirects directly to tenant subdomain when user has single tenant', function () {
    // Create a user with a single tenant
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $user->tenants()->attach($tenant, ['role' => 'admin']);

    // Test the LoginResponse directly
    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $loginResponse = new LoginResponse;
    $response = $loginResponse->toResponse($request);

    // Should redirect to tenant subdomain with token
    expect($response->isRedirect())->toBeTrue();

    $location = $response->headers->get('Location');
    expect($location)->toContain('acme.kasamahr.test')
        ->and($location)->toContain('token=');

    // Verify token was created
    expect(TenantRedirectToken::count())->toBe(1);
});

it('shows only user tenants on tenant selection page', function () {
    // Create a user with specific tenants
    $user = User::factory()->withoutTwoFactor()->create();

    $tenant1 = Tenant::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme']);
    $tenant2 = Tenant::factory()->create(['name' => 'Globex Inc', 'slug' => 'globex']);
    $otherTenant = Tenant::factory()->create(['name' => 'Other Corp', 'slug' => 'other']);

    $user->tenants()->attach($tenant1, ['role' => 'admin']);
    $user->tenants()->attach($tenant2, ['role' => 'employee']);

    // Test the controller directly without rendering
    $request = Request::create('/select-tenant', 'GET');
    $request->setUserResolver(fn () => $user);

    $controller = new TenantSelectorController;
    $inertiaResponse = $controller->index($request);

    // Use reflection to access protected properties for testing
    $reflection = new ReflectionClass($inertiaResponse);

    $componentProperty = $reflection->getProperty('component');
    $componentProperty->setAccessible(true);
    expect($componentProperty->getValue($inertiaResponse))->toBe('TenantSelector');

    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($inertiaResponse);

    $tenants = $props['tenants'];
    expect($tenants)->toHaveCount(2);
    expect($tenants[0]['name'])->toBe('Acme Corp');
    expect($tenants[1]['name'])->toBe('Globex Inc');
});

it('validates tenant selection creates secure redirect token', function () {
    // Create a user with a tenant
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $user->tenants()->attach($tenant, ['role' => 'admin']);

    // Select the tenant using named route
    $response = $this->actingAs($user)->post(route('tenant.select.submit', $tenant));

    // Should create a redirect token
    $token = TenantRedirectToken::first();

    expect($token)->not->toBeNull()
        ->and($token->user_id)->toBe($user->id)
        ->and($token->tenant_id)->toBe($tenant->id)
        ->and($token->expires_at)->toBeGreaterThan(now())
        ->and($token->expires_at)->toBeLessThanOrEqual(now()->addMinutes(6));

    // Should redirect with token
    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toContain('acme.kasamahr.test')
        ->and($location)->toContain('token='.$token->token);
});

it('denies tenant selection for non-member users', function () {
    // Create a user without tenant membership
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    // Try to select the tenant using named route
    $response = $this->actingAs($user)->post(route('tenant.select.submit', $tenant));

    // Should be forbidden
    $response->assertForbidden();
});

it('authenticates from token on tenant subdomain', function () {
    // Create user and tenant
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $user->tenants()->attach($tenant, ['role' => 'admin']);

    // Create a valid token
    $token = TenantRedirectToken::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'token' => 'valid-test-token',
        'expires_at' => now()->addMinutes(5),
    ]);

    // Bind the tenant to the container (simulating ResolveTenant middleware)
    app()->instance('tenant', $tenant);

    // Create a request simulating access to tenant subdomain with token
    $request = Request::create('http://acme.kasamahr.test/?token=valid-test-token', 'GET');

    $middleware = new AuthenticateFromToken;

    $response = $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // Should redirect to clean URL (without query string)
    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toBe('http://acme.kasamahr.test');

    // Token should be deleted after use
    expect(TenantRedirectToken::where('token', 'valid-test-token')->exists())->toBeFalse();

    // User should be authenticated
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});
