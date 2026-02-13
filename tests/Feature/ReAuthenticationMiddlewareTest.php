<?php

/**
 * Tests for Re-Authentication Middleware Application.
 *
 * These tests verify that sensitive actions (user role changes, user deactivation)
 * require password confirmation via the `tenant.password.confirm` middleware, while
 * regular endpoints do not require re-authentication.
 *
 * The password confirmation is valid for 3 hours (10800 seconds) as configured
 * in config/auth.php.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\TenantUserController;
use App\Http\Requests\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantForReAuth(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createUserInTenantForReAuth(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create a mock UpdateTenantUserRequest with validated data.
 */
function createUpdateRequestForReAuth(array $data, User $user, int $userId): UpdateTenantUserRequest
{
    $request = UpdateTenantUserRequest::create("/api/users/{$userId}", 'PATCH', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Create and set the validator manually
    $validator = Validator::make($data, [
        'role' => ['required', new \Illuminate\Validation\Rules\Enum(TenantUserRole::class)],
    ]);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    $this->withoutVite();
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Re-Authentication Middleware Configuration', function () {
    it('has tenant.password.confirm middleware applied to role change route', function () {
        $routeCollection = Route::getRoutes();

        // Find the route for user update
        $updateRoute = null;
        foreach ($routeCollection->getRoutes() as $route) {
            if (str_contains($route->uri(), 'api/users/{user}') && in_array('PATCH', $route->methods())) {
                $updateRoute = $route;
                break;
            }
        }

        expect($updateRoute)->not->toBeNull();

        // Check that tenant.password.confirm middleware is applied
        $middleware = $updateRoute->middleware();
        expect($middleware)->toContain('tenant.password.confirm');
    });

    it('has tenant.password.confirm middleware applied to user deactivation route', function () {
        $routeCollection = Route::getRoutes();

        // Find the route for user deletion
        $deleteRoute = null;
        foreach ($routeCollection->getRoutes() as $route) {
            if (str_contains($route->uri(), 'api/users/{user}') && in_array('DELETE', $route->methods())) {
                $deleteRoute = $route;
                break;
            }
        }

        expect($deleteRoute)->not->toBeNull();

        // Check that tenant.password.confirm middleware is applied
        $middleware = $deleteRoute->middleware();
        expect($middleware)->toContain('tenant.password.confirm');
    });

    it('does NOT have tenant.password.confirm middleware on user listing route', function () {
        $routeCollection = Route::getRoutes();

        // Find the route for user listing
        $indexRoute = null;
        foreach ($routeCollection->getRoutes() as $route) {
            if (
                $route->uri() === '{tenant}/api/users' ||
                (str_ends_with($route->uri(), 'api/users') && in_array('GET', $route->methods()))
            ) {
                $indexRoute = $route;
                break;
            }
        }

        expect($indexRoute)->not->toBeNull();

        // Check that tenant.password.confirm middleware is NOT applied
        $middleware = $indexRoute->middleware();
        expect($middleware)->not->toContain('tenant.password.confirm');
    });

    it('does NOT have tenant.password.confirm middleware on user invite route', function () {
        $routeCollection = Route::getRoutes();

        // Find the route for user invite
        $inviteRoute = null;
        foreach ($routeCollection->getRoutes() as $route) {
            if (str_contains($route->uri(), 'api/users/invite') && in_array('POST', $route->methods())) {
                $inviteRoute = $route;
                break;
            }
        }

        expect($inviteRoute)->not->toBeNull();

        // Check that tenant.password.confirm middleware is NOT applied
        $middleware = $inviteRoute->middleware();
        expect($middleware)->not->toContain('tenant.password.confirm');
    });
});

describe('Password Confirmation Functionality', function () {
    it('allows password confirmation submission to the confirm endpoint', function () {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('password.confirm.store'), [
                'password' => 'correct-password',
            ]);

        // Fortify returns 201 Created on successful password confirmation
        $response->assertStatus(201);
    });

    it('returns error on invalid password', function () {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('password.confirm.store'), [
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    });
});

describe('Password Confirmation Timeout', function () {
    it('has password timeout configured to 3 hours (10800 seconds)', function () {
        $timeout = config('auth.password_timeout');

        expect($timeout)->toBe(10800);
    });
});

describe('Controller Actions with Re-Authentication', function () {
    it('allows Admin to update user role via controller when authorized', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForReAuth($tenant);

        $admin = createUserInTenantForReAuth($tenant, TenantUserRole::Admin);
        $employee = createUserInTenantForReAuth($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);

        // Test the controller directly (bypassing middleware for unit testing)
        $controller = new TenantUserController;
        $request = createUpdateRequestForReAuth([
            'role' => TenantUserRole::HrStaff->value,
        ], $admin, $employee->id);

        $response = $controller->update($request, $employee);

        $data = $response->toArray(request());
        expect($data['role'])->toBe(TenantUserRole::HrStaff->value);
    });

    it('allows Admin to deactivate user via controller when authorized', function () {
        $tenant = Tenant::factory()->create();
        bindTenantForReAuth($tenant);

        $admin = createUserInTenantForReAuth($tenant, TenantUserRole::Admin);
        $employee = createUserInTenantForReAuth($tenant, TenantUserRole::Employee);

        // Set up request context for the controller
        $request = Request::create("/api/users/{$employee->id}", 'DELETE');
        $request->setUserResolver(fn () => $admin);
        app()->instance('request', $request);

        $this->actingAs($admin);

        // Test the controller directly (bypassing middleware for unit testing)
        $controller = new TenantUserController;
        $response = $controller->destroy($employee);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('User removed from tenant successfully.');

        // Verify the user is no longer attached to the tenant
        $employee->refresh();
        expect($employee->tenants->pluck('id')->contains($tenant->id))->toBeFalse();
    });
});
