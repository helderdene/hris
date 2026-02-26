<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\My\MyProfileController;
use App\Http\Requests\UpdateMyProfileRequest;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForProfile(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createEmployeeUserForProfile(Tenant $tenant, array $employeeAttributes = []): array
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $employee = Employee::factory()->create(array_merge([
        'user_id' => $user->id,
    ], $employeeAttributes));

    return [$user, $employee];
}

function getInertiaResponseDataForProfile(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

function getInertiaComponentForProfile(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

function callProfileShowController(User $user): \Inertia\Response
{
    $controller = app(MyProfileController::class);
    $request = Request::create('/my/profile', 'GET');
    $request->setUserResolver(fn () => $user);
    app()->instance('request', $request);

    return $controller->show($request);
}

function callProfileUpdateController(User $user, array $data): \Illuminate\Http\RedirectResponse
{
    $controller = app(MyProfileController::class);
    $request = UpdateMyProfileRequest::create('/my/profile', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = app('validator')->make($data, (new UpdateMyProfileRequest)->rules());
    $request->setValidator($validator);

    app()->instance('request', $request);

    return $controller->update($request);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('My Profile Show', function () {
    it('displays the employee profile with all sections', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant, [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'tin' => '123-456-789',
            'sss_number' => '12-3456789-0',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Manila',
                'province' => 'Metro Manila',
            ],
            'emergency_contact' => [
                'name' => 'Maria Dela Cruz',
                'relationship' => 'spouse',
                'phone' => '09181234567',
            ],
        ]);

        $this->actingAs($user);

        $response = callProfileShowController($user);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponentForProfile($response))->toBe('My/Profile');

        $data = getInertiaResponseDataForProfile($response);
        expect($data['employee'])->not->toBeNull();
        expect($data['employee']['first_name'])->toBe('Juan');
        expect($data['employee']['last_name'])->toBe('Dela Cruz');
        expect($data['employee']['email'])->toBe('juan@example.com');
        expect($data['employee']['phone'])->toBe('09171234567');
        expect($data['employee']['tin'])->toBe('123-456-789');
        expect($data['employee']['address']['street'])->toBe('123 Main St');
        expect($data['employee']['emergency_contact']['name'])->toBe('Maria Dela Cruz');
        expect($data['employee'])->toHaveKeys(['business_card_enabled', 'business_card_token']);
    });

    it('includes business card data when enabled', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant, [
            'business_card_enabled' => true,
            'business_card_token' => 'test-token-123',
        ]);

        $this->actingAs($user);

        $response = callProfileShowController($user);
        $data = getInertiaResponseDataForProfile($response);

        expect($data['employee']['business_card_enabled'])->toBeTrue();
        expect($data['employee']['business_card_token'])->toBe('test-token-123');
    });

    it('returns null employee when user has no linked employee', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $this->actingAs($user);

        $response = callProfileShowController($user);

        $data = getInertiaResponseDataForProfile($response);
        expect($data['employee'])->toBeNull();
    });
});

describe('My Profile Update', function () {
    it('updates contact information', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant);

        $this->actingAs($user);

        callProfileUpdateController($user, [
            'phone' => '09991112222',
            'email' => 'newemail@example.com',
            'address' => [
                'street' => '456 New St',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'barangay' => 'Brgy 1',
                'postal_code' => '1100',
            ],
        ]);

        $employee->refresh();
        expect($employee->phone)->toBe('09991112222');
        expect($employee->email)->toBe('newemail@example.com');
        expect($employee->address['street'])->toBe('456 New St');
        expect($employee->address['city'])->toBe('Quezon City');
    });

    it('updates emergency contact', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant);

        $this->actingAs($user);

        callProfileUpdateController($user, [
            'emergency_contact' => [
                'name' => 'Pedro Santos',
                'relationship' => 'parent',
                'phone' => '09171112222',
            ],
        ]);

        $employee->refresh();
        expect($employee->emergency_contact['name'])->toBe('Pedro Santos');
        expect($employee->emergency_contact['relationship'])->toBe('parent');
        expect($employee->emergency_contact['phone'])->toBe('09171112222');
    });

    it('updates government IDs', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant);

        $this->actingAs($user);

        callProfileUpdateController($user, [
            'tin' => '999-888-777',
            'sss_number' => '99-8888777-6',
            'philhealth_number' => '99-887766554-3',
            'pagibig_number' => '9988-7766-5544',
            'umid' => '9988-7766554-3',
            'passport_number' => 'P12345678',
            'drivers_license' => 'N01-23-456789',
        ]);

        $employee->refresh();
        expect($employee->tin)->toBe('999-888-777');
        expect($employee->sss_number)->toBe('99-8888777-6');
        expect($employee->philhealth_number)->toBe('99-887766554-3');
        expect($employee->pagibig_number)->toBe('9988-7766-5544');
        expect($employee->umid)->toBe('9988-7766554-3');
        expect($employee->passport_number)->toBe('P12345678');
        expect($employee->drivers_license)->toBe('N01-23-456789');
    });

    it('validates email format', function () {
        $rules = (new UpdateMyProfileRequest)->rules();
        $validator = app('validator')->make(
            ['email' => 'not-an-email'],
            $rules,
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('email'))->toBeTrue();
    });

    it('validates phone max length', function () {
        $rules = (new UpdateMyProfileRequest)->rules();
        $validator = app('validator')->make(
            ['phone' => str_repeat('x', 25)],
            $rules,
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('phone'))->toBeTrue();
    });

    it('does not update read-only fields', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant, [
            'first_name' => 'Original',
            'last_name' => 'Name',
        ]);

        $this->actingAs($user);

        callProfileUpdateController($user, [
            'first_name' => 'Hacked',
            'last_name' => 'User',
            'department_id' => 999,
            'employment_status' => 'terminated',
            'basic_salary' => 999999,
            'phone' => '09991112222',
        ]);

        $employee->refresh();
        expect($employee->first_name)->toBe('Original');
        expect($employee->last_name)->toBe('Name');
        expect($employee->phone)->toBe('09991112222');
    });

    it('allows nullable fields to be cleared', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantContextForProfile($tenant);

        [$user, $employee] = createEmployeeUserForProfile($tenant, [
            'phone' => '09171234567',
            'tin' => '123-456-789',
        ]);

        $this->actingAs($user);

        callProfileUpdateController($user, [
            'phone' => null,
            'tin' => null,
        ]);

        $employee->refresh();
        expect($employee->phone)->toBeNull();
        expect($employee->tin)->toBeNull();
    });
});
