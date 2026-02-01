<?php

use App\Enums\TenantUserRole;
use App\Http\Middleware\EnsureRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * Helper to bind a tenant to the application container.
 */
function bindTenantForMiddleware(?Tenant $tenant): void
{
    if ($tenant !== null) {
        app()->instance('tenant', $tenant);
    } else {
        app()->forgetInstance('tenant');
    }
}

/**
 * Helper to create a mock request with authenticated user.
 */
function createAuthenticatedRequest(User $user): Request
{
    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

/**
 * Helper to execute middleware and get response.
 *
 * @param  array<string>  $roles
 */
function executeMiddleware(Request $request, array $roles): Response
{
    $middleware = new EnsureRole;
    $rolesString = implode(',', $roles);

    return $middleware->handle($request, function () {
        return new Response('OK', 200);
    }, ...$roles);
}

describe('EnsureRole Middleware - Access Control', function () {
    it('allows access when user has required role', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($user);

        $response = executeMiddleware($request, ['admin']);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');
    });

    it('blocks access when user lacks required role', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Employee->value]);

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($user);

        expect(fn () => executeMiddleware($request, ['admin']))
            ->toThrow(HttpException::class);
    });

    it('allows Super Admin regardless of role', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::factory()->create();
        // Super Admin not attached to tenant, should still have access

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($superAdmin);

        $response = executeMiddleware($request, ['admin']);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');
    });

    it('handles multiple allowed roles and allows matching user', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::HrManager->value]);

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($user);

        $response = executeMiddleware($request, ['admin', 'hr_manager', 'hr_staff']);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getContent())->toBe('OK');
    });

    it('blocks access when user has none of the allowed roles', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Supervisor->value]);

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($user);

        expect(fn () => executeMiddleware($request, ['admin', 'hr_manager']))
            ->toThrow(HttpException::class);
    });

    it('returns 403 status code when access is denied', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Employee->value]);

        bindTenantForMiddleware($tenant);
        $request = createAuthenticatedRequest($user);

        try {
            executeMiddleware($request, ['admin']);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    });
});

describe('EnsureRole Middleware - Edge Cases', function () {
    it('blocks access when no tenant context exists', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantUserRole::Admin->value]);

        // No tenant bound
        bindTenantForMiddleware(null);
        $request = createAuthenticatedRequest($user);

        expect(fn () => executeMiddleware($request, ['admin']))
            ->toThrow(HttpException::class);
    });

    it('blocks access when user is not authenticated', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForMiddleware($tenant);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        expect(fn () => executeMiddleware($request, ['admin']))
            ->toThrow(HttpException::class);
    });

    it('allows Super Admin even without tenant context', function () {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        // No tenant bound
        bindTenantForMiddleware(null);
        $request = createAuthenticatedRequest($superAdmin);

        $response = executeMiddleware($request, ['admin']);

        expect($response->getStatusCode())->toBe(200);
    });

    it('checks role against current tenant not other tenants', function () {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // User is Admin in tenant1 but Employee in tenant2
        $user->tenants()->attach($tenant1->id, ['role' => TenantUserRole::Admin->value]);
        $user->tenants()->attach($tenant2->id, ['role' => TenantUserRole::Employee->value]);

        // Current context is tenant2 where user is Employee
        bindTenantForMiddleware($tenant2);
        $request = createAuthenticatedRequest($user);

        // Should fail because in tenant2, user is Employee, not Admin
        expect(fn () => executeMiddleware($request, ['admin']))
            ->toThrow(HttpException::class);

        // Switch to tenant1 where user is Admin
        bindTenantForMiddleware($tenant1);

        $response = executeMiddleware($request, ['admin']);
        expect($response->getStatusCode())->toBe(200);
    });
});
