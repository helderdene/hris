<?php

use App\Actions\InviteUserAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\TenantUserController;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantToApp(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createUserInTenant(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a mock InviteUserRequest with validated data.
 */
function createInviteRequest(array $data, User $user): InviteUserRequest
{
    $request = InviteUserRequest::create('/api/users/invite', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    // Create and set the validator manually
    $validator = Validator::make($data, [
        'email' => ['required', 'email', 'max:255'],
        'name' => ['required', 'string', 'max:255'],
        'role' => ['required', new \Illuminate\Validation\Rules\Enum(TenantUserRole::class)],
    ]);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a mock UpdateTenantUserRequest with validated data.
 */
function createUpdateRequest(array $data, User $user, int $userId): UpdateTenantUserRequest
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
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Tenant User Management API', function () {
    it('allows Admin to list tenant users', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $employee1 = createUserInTenant($tenant, TenantUserRole::Employee);
        $employee2 = createUserInTenant($tenant, TenantUserRole::HrStaff);

        $this->actingAs($admin);

        $controller = new TenantUserController;
        $response = $controller->index();

        expect($response->count())->toBe(3);

        // Get the underlying resource collection
        $collection = $response->collection;
        expect($collection)->toHaveCount(3);

        // Check structure of first resource by converting to array
        $firstResource = $response->collection->first();
        $resourceArray = $firstResource->toArray(request());

        expect($resourceArray)->toHaveKeys(['id', 'name', 'email', 'role', 'role_label', 'invited_at', 'invitation_accepted_at']);
    });

    it('allows Admin to invite new user', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);

        $this->actingAs($admin);

        // Use the InviteUserAction directly instead of going through controller
        // This tests the business logic without form request complexity
        $action = new InviteUserAction;
        $user = $action->execute(
            email: 'newuser@example.com',
            name: 'New User',
            role: TenantUserRole::Employee,
            tenantId: $tenant->id,
            inviterId: $admin->id
        );

        expect($user)->toBeInstanceOf(User::class);
        expect($user->email)->toBe('newuser@example.com');
        expect($user->name)->toBe('New User');

        // Verify the user was attached to tenant with correct role
        $tenantUser = TenantUser::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser)->not->toBeNull();
        expect($tenantUser->role)->toBe(TenantUserRole::Employee);

        // Verify notification was sent
        Notification::assertSentTo($user, UserInvitation::class);
    });

    it('allows Admin to update user role via controller', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $employee = createUserInTenant($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);

        $controller = new TenantUserController;
        $request = createUpdateRequest([
            'role' => TenantUserRole::HrStaff->value,
        ], $admin, $employee->id);

        $response = $controller->update($request, $employee);

        $data = $response->toArray(request());
        expect($data['role'])->toBe(TenantUserRole::HrStaff->value);

        // Verify the role was updated in the pivot
        $tenantUser = TenantUser::where('user_id', $employee->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        expect($tenantUser->role)->toBe(TenantUserRole::HrStaff);
    });

    it('allows Admin to deactivate user (remove from tenant)', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $employee = createUserInTenant($tenant, TenantUserRole::Employee);

        // Create a request with the admin as the authenticated user
        $request = Request::create("/api/users/{$employee->id}", 'DELETE');
        $request->setUserResolver(fn () => $admin);
        app()->instance('request', $request);

        $this->actingAs($admin);

        $controller = new TenantUserController;
        $response = $controller->destroy($employee);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toBe('User removed from tenant successfully.');

        // Verify the user is no longer attached to the tenant
        $employee->load('tenants');
        expect($employee->tenants->pluck('id')->contains($tenant->id))->toBeFalse();
    });

    it('denies non-Admin access to user management endpoints via gate', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        // Test with HR Manager (should be denied)
        $hrManager = createUserInTenant($tenant, TenantUserRole::HrManager);

        expect(Gate::forUser($hrManager)->allows('can-manage-users'))->toBeFalse();

        // Test with Employee (should be denied)
        $employee = createUserInTenant($tenant, TenantUserRole::Employee);

        expect(Gate::forUser($employee)->allows('can-manage-users'))->toBeFalse();

        // Test with HR Staff (should be denied)
        $hrStaff = createUserInTenant($tenant, TenantUserRole::HrStaff);

        expect(Gate::forUser($hrStaff)->allows('can-manage-users'))->toBeFalse();

        // Test with Admin (should be allowed)
        $admin = createUserInTenant($tenant, TenantUserRole::Admin);

        expect(Gate::forUser($admin)->allows('can-manage-users'))->toBeTrue();
    });

    it('allows Super Admin to access user management endpoints', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $superAdmin = User::factory()->superAdmin()->create();

        expect(Gate::forUser($superAdmin)->allows('can-manage-users'))->toBeTrue();
    });

    it('validates invite request - rejects missing fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create request with missing fields
        $request = InviteUserRequest::create('/api/users/invite', 'POST', []);
        $request->setContainer(app());

        $validator = validator($request->all(), (new InviteUserRequest)->rules());

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('role'))->toBeTrue();
    });

    it('validates invite request - rejects invalid email format', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $request = InviteUserRequest::create('/api/users/invite', 'POST', [
            'email' => 'not-an-email',
            'name' => 'Test',
            'role' => TenantUserRole::Employee->value,
        ]);
        $request->setContainer(app());

        $validator = validator($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', new \Illuminate\Validation\Rules\Enum(TenantUserRole::class)],
        ]);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('validates invite request - rejects invalid role', function () {
        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $request = InviteUserRequest::create('/api/users/invite', 'POST', [
            'email' => 'valid@example.com',
            'name' => 'Test',
            'role' => 'invalid_role',
        ]);
        $request->setContainer(app());

        $validator = validator($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', new \Illuminate\Validation\Rules\Enum(TenantUserRole::class)],
        ]);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('role'))->toBeTrue();
    });

    it('prevents inviting user already in tenant', function () {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        bindTenantToApp($tenant);

        $admin = createUserInTenant($tenant, TenantUserRole::Admin);
        $existingUser = createUserInTenant($tenant, TenantUserRole::Employee);

        $this->actingAs($admin);

        // Check using the custom validation rule from InviteUserRequest
        $inviteRequest = new InviteUserRequest;
        $rules = $inviteRequest->rules();

        $validator = validator([
            'email' => $existingUser->email,
            'name' => 'Duplicate User',
            'role' => TenantUserRole::HrStaff->value,
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });
});
