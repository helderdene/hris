<?php

/**
 * Strategic Integration Tests for Multi-Tenant Architecture
 *
 * These tests fill critical gaps in the test coverage for the multi-tenant
 * feature, focusing on end-to-end flows, cross-cutting concerns, and security.
 */

use App\Enums\TenantUserRole;
use App\Http\Middleware\AuthenticateFromToken;
use App\Http\Middleware\EnsureTenantMember;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing (needed by seeders during registration)
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
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

/**
 * End-to-end: Complete tenant registration and first access flow
 *
 * Tests the full lifecycle: user registers tenant -> schema provisioned ->
 * redirect to subdomain with token -> token authenticates user
 */
it('completes full tenant registration and first access flow end-to-end', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // Mock TenantDatabaseManager for this test
    $mockManager = Mockery::mock(TenantDatabaseManager::class);
    $mockManager->shouldReceive('createSchema');
    $mockManager->shouldReceive('migrateSchema');
    $this->app->instance(TenantDatabaseManager::class, $mockManager);

    // Step 1: User registers a new tenant
    $response = $this->actingAs($user)->post(route('tenant.register.store'), [
        'name' => 'Complete Flow Corp',
        'slug' => 'complete-flow',
        'business_info' => [
            'company_name' => 'Complete Flow Corporation',
            'address' => '100 Test Street',
        ],
    ]);

    // Should redirect to tenant subdomain
    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('complete-flow.kasamahr.test');

    // Extract the token from the redirect URL
    parse_str(parse_url($location, PHP_URL_QUERY), $queryParams);
    $tokenString = $queryParams['token'];
    expect($tokenString)->not->toBeEmpty();

    // Step 2: Verify token was created correctly
    $token = TenantRedirectToken::where('token', $tokenString)->first();
    expect($token)->not->toBeNull()
        ->and($token->user_id)->toBe($user->id)
        ->and($token->isValid())->toBeTrue();

    // Step 3: Verify tenant was created with correct data
    $tenant = Tenant::where('slug', 'complete-flow')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Complete Flow Corp');

    // Step 4: Verify user was attached as admin
    $membership = $tenant->users()->where('user_id', $user->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->pivot->role)->toBe(TenantUserRole::Admin);
});

/**
 * End-to-end: Multi-tenant user switching between organizations
 *
 * Tests that a user belonging to multiple tenants can switch between them.
 */
it('allows multi-tenant user to switch between organizations', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $tenant1 = Tenant::factory()->create(['name' => 'Alpha Corp', 'slug' => 'alpha']);
    $tenant2 = Tenant::factory()->create(['name' => 'Beta Inc', 'slug' => 'beta']);

    $user->tenants()->attach($tenant1->id, ['role' => 'admin']);
    $user->tenants()->attach($tenant2->id, ['role' => 'employee']);

    // Step 1: Select first tenant
    $response = $this->actingAs($user)->post(route('tenant.select.submit', $tenant1));
    $response->assertRedirect();

    $location1 = $response->headers->get('Location');
    expect($location1)->toContain('alpha.kasamahr.test');

    // Verify token for tenant1
    $token1 = TenantRedirectToken::latest()->first();
    expect($token1->tenant_id)->toBe($tenant1->id);

    // Clean up token
    $token1->delete();

    // Step 2: Go back and select second tenant
    $response = $this->actingAs($user)->post(route('tenant.select.submit', $tenant2));
    $response->assertRedirect();

    $location2 = $response->headers->get('Location');
    expect($location2)->toContain('beta.kasamahr.test');

    // Verify token for tenant2
    $token2 = TenantRedirectToken::latest()->first();
    expect($token2->tenant_id)->toBe($tenant2->id);
});

/**
 * Security: Expired redirect token does not authenticate user
 */
it('rejects expired redirect tokens', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'expired-test']);
    $user->tenants()->attach($tenant->id, ['role' => 'admin']);

    // Create an expired token
    $token = TenantRedirectToken::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'token' => 'expired-token-string',
        'expires_at' => now()->subMinutes(10), // Expired 10 minutes ago
    ]);

    // Bind tenant to container
    app()->instance('tenant', $tenant);

    // Attempt authentication with expired token
    $request = Request::create('http://expired-test.kasamahr.test/?token=expired-token-string', 'GET');

    $middleware = new AuthenticateFromToken;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // User should NOT be authenticated
    expect(Auth::check())->toBeFalse();

    // Token should still exist (not deleted because it failed validation)
    expect(TenantRedirectToken::where('token', 'expired-token-string')->exists())->toBeTrue();
});

/**
 * Security: Redirect token cannot be used on wrong tenant subdomain
 */
it('rejects redirect token used on wrong tenant subdomain', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $correctTenant = Tenant::factory()->create(['slug' => 'correct-tenant']);
    $wrongTenant = Tenant::factory()->create(['slug' => 'wrong-tenant']);

    $user->tenants()->attach($correctTenant->id, ['role' => 'admin']);
    $user->tenants()->attach($wrongTenant->id, ['role' => 'employee']);

    // Create token for correct tenant
    $token = TenantRedirectToken::create([
        'user_id' => $user->id,
        'tenant_id' => $correctTenant->id,
        'token' => 'tenant-specific-token',
        'expires_at' => now()->addMinutes(5),
    ]);

    // Bind the WRONG tenant to container (simulating access to wrong subdomain)
    app()->instance('tenant', $wrongTenant);

    // Attempt authentication with token meant for different tenant
    $request = Request::create('http://wrong-tenant.kasamahr.test/?token=tenant-specific-token', 'GET');

    $middleware = new AuthenticateFromToken;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // User should NOT be authenticated
    expect(Auth::check())->toBeFalse();
});

/**
 * Security: Token rejected when user membership is revoked after token generation
 */
it('rejects token when user membership was revoked after token creation', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'revoked-test']);
    $user->tenants()->attach($tenant->id, ['role' => 'admin']);

    // Create a valid token
    $token = TenantRedirectToken::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'token' => 'valid-but-revoked-access',
        'expires_at' => now()->addMinutes(5),
    ]);

    // REVOKE the user's membership after token was created
    $user->tenants()->detach($tenant->id);

    // Bind tenant to container
    app()->instance('tenant', $tenant);

    // Attempt authentication with token
    $request = Request::create('http://revoked-test.kasamahr.test/?token=valid-but-revoked-access', 'GET');

    $middleware = new AuthenticateFromToken;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // User should NOT be authenticated because membership was revoked
    expect(Auth::check())->toBeFalse();

    // Token should be deleted (consumed during failed validation)
    expect(TenantRedirectToken::where('token', 'valid-but-revoked-access')->exists())->toBeFalse();
});

/**
 * Security: EnsureTenantMember middleware blocks non-member access
 */
it('blocks authenticated non-member from accessing tenant routes via middleware', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'restricted-org']);

    // User is NOT a member of this tenant
    // Bind tenant to container
    app()->instance('tenant', $tenant);

    // Create request with authenticated user
    $request = Request::create('http://restricted-org.kasamahr.test/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureTenantMember;

    // Should abort with 403
    expect(fn () => $middleware->handle($request, function ($req) {
        return new Response('OK');
    }))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

/**
 * Integration: User with no tenants redirected to organization registration after login
 */
it('redirects user with no tenants to organization registration after login', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    // User has NO tenant memberships

    // Test the LoginResponse directly
    $request = Request::create('/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $loginResponse = new \App\Http\Responses\LoginResponse;
    $response = $loginResponse->toResponse($request);

    // Should redirect to organization registration (users without tenants must create one)
    expect($response->isRedirect())->toBeTrue();

    $location = $response->headers->get('Location');
    expect($location)->toContain('/register-organization');
    // Should NOT contain subdomain or token
    expect($location)->not->toContain('.kasamahr.test');
    expect($location)->not->toContain('token=');
});

/**
 * Integration: Token is single-use and deleted after successful authentication
 */
it('deletes token after successful authentication preventing reuse', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'single-use-test']);
    $user->tenants()->attach($tenant->id, ['role' => 'admin']);

    // Create a valid token
    TenantRedirectToken::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'token' => 'single-use-token',
        'expires_at' => now()->addMinutes(5),
    ]);

    // Bind tenant
    app()->instance('tenant', $tenant);

    // First authentication attempt
    $request = Request::create('http://single-use-test.kasamahr.test/?token=single-use-token', 'GET');

    $middleware = new AuthenticateFromToken;
    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // Should be authenticated
    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);

    // Token should be deleted
    expect(TenantRedirectToken::where('token', 'single-use-token')->exists())->toBeFalse();

    // Log out for second attempt
    Auth::logout();

    // Second authentication attempt with same token
    $request2 = Request::create('http://single-use-test.kasamahr.test/?token=single-use-token', 'GET');

    $middleware->handle($request2, function ($req) {
        return new Response('OK');
    });

    // Should NOT be authenticated (token was already used)
    expect(Auth::check())->toBeFalse();
});

/**
 * Security: Super admin can access tenant without explicit membership
 */
it('allows super admin to access any tenant subdomain', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'super-access-test']);

    // Super admin is NOT a member of this tenant
    // Bind tenant to container
    app()->instance('tenant', $tenant);

    // Create request with authenticated super admin
    $request = Request::create('http://super-access-test.kasamahr.test/dashboard', 'GET');
    $request->setUserResolver(fn () => $superAdmin);

    $middleware = new EnsureTenantMember;

    // Should pass without exception
    $response = $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
});

/**
 * Integration: Verify correct tenant context is available throughout request
 */
it('maintains correct tenant context throughout middleware chain', function () {
    $manager = app(TenantDatabaseManager::class);

    $tenant = Tenant::factory()->create(['slug' => 'context-test', 'name' => 'Context Test Corp']);

    // Create schema
    $manager->createSchema($tenant);

    // Create request simulating tenant subdomain access
    $request = Request::create('http://context-test.kasamahr.test/api/test', 'GET');

    // Run ResolveTenant middleware
    $resolveTenant = new \App\Http\Middleware\ResolveTenant;
    $resolveTenant->handle($request, function ($req) {
        return new Response('OK');
    });

    // Verify tenant() helper returns correct tenant
    expect(tenant())->not->toBeNull();
    expect(tenant()->slug)->toBe('context-test');
    expect(tenant()->name)->toBe('Context Test Corp');

    // Run SwitchTenantDatabase middleware
    $switchDb = new \App\Http\Middleware\SwitchTenantDatabase($manager);
    $switchDb->handle($request, function ($req) {
        return new Response('OK');
    });

    // Verify tenant connection is configured correctly
    if (config('database.default') === 'sqlite') {
        $expectedPath = database_path('tenant_context-test.sqlite');
        expect(config('database.connections.tenant.database'))->toBe($expectedPath);

        // Clean up
        @unlink($expectedPath);
    } else {
        expect(config('database.connections.tenant.database'))->toBe('kasamahr_tenant_context-test');
    }
});
