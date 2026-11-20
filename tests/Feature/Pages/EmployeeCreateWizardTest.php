<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);

    $admin = User::factory()->create();
    $admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $this->actingAs($admin);

    $this->url = "http://{$this->tenant->slug}.kasamahr.test/employees";

    // What the wizard posts when only the Essentials step is filled in.
    $this->essentialsOnly = [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'employee_number' => 'EMP-0001',
        'hire_date' => '2026-11-20',
        'email' => 'maria@example.com',
        'middle_name' => '',
        'date_of_birth' => '',
        'gender' => '',
        'department_id' => null,
        'position_id' => null,
        'work_location_id' => null,
        'supervisor_id' => null,
        'employment_type' => 'regular',
        'employment_status' => 'active',
        'regularization_date' => '',
        'basic_salary' => null,
        'pay_frequency' => '',
        'tin' => '',
        'phone' => '',
        'address' => ['street' => '', 'barangay' => '', 'city' => '', 'province' => '', 'postal_code' => ''],
        'emergency_contact' => ['name' => '', 'relationship' => '', 'phone' => ''],
    ];
});

it('creates an employee from the essentials step alone', function () {
    $this->post($this->url, $this->essentialsOnly)->assertSessionHasNoErrors()->assertRedirect();

    expect(Employee::query()->where('employee_number', 'EMP-0001')->value('email'))->toBe('maria@example.com');
});

it('reports each missing essential under its own field', function (string $field) {
    $this->post($this->url, [...$this->essentialsOnly, $field => ''])->assertSessionHasErrors($field);

    expect(Employee::count())->toBe(0);
})->with(['first_name', 'last_name', 'employee_number', 'hire_date', 'email']);

it('reports the employee limit under the key the wizard shows as a banner', function () {
    mock(FeatureGateService::class, function ($mock) {
        $mock->shouldReceive('isWithinEmployeeLimit')->andReturnFalse();
        $mock->shouldReceive('getEffectiveLimit')->andReturn(5);
    });

    $this->post($this->url, $this->essentialsOnly)->assertSessionHasErrors('limit');

    expect(Employee::count())->toBe(0);
});
