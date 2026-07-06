<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

it('provides the employee list on the create page even when the acting user has no employee record', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    Employee::factory()->count(3)->create();

    $response = $this->get("{$this->baseUrl}/recruitment/job-postings/create");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Recruitment/JobPostings/Create')
        ->where('employee', null)
        ->has('employees', 3)
        ->has('employees.0.id')
        ->has('employees.0.full_name')
        ->has('employees.0.employee_number')
    );
});

it('pre-fills the acting user employee when they are linked to one', function () {
    $this->withoutVite();

    $employee = Employee::factory()->create(['user_id' => $this->user->id]);
    Employee::factory()->count(2)->create();

    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/recruitment/job-postings/create");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Recruitment/JobPostings/Create')
        ->where('employee.id', $employee->id)
        ->has('employees', 3)
    );
});
