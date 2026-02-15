<?php

use App\Enums\TenantUserRole;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

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
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-employees', fn () => true);

    $this->employee = Employee::factory()->create();
});

it('exports DTR records as XLSX', function () {
    DailyTimeRecord::factory()
        ->for($this->employee)
        ->forDate(now()->subDays(2))
        ->create();

    $response = $this->actingAs($this->user)
        ->get("{$this->baseUrl}/time-attendance/dtr/{$this->employee->id}/export?format=xlsx&date_from=".now()->subMonth()->toDateString().'&date_to='.now()->toDateString());

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('exports DTR records as PDF', function () {
    DailyTimeRecord::factory()
        ->for($this->employee)
        ->forDate(now()->subDays(2))
        ->create();

    $response = $this->actingAs($this->user)
        ->get("{$this->baseUrl}/time-attendance/dtr/{$this->employee->id}/export?format=pdf&date_from=".now()->subMonth()->toDateString().'&date_to='.now()->toDateString());

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

it('defaults to XLSX format when no format is specified', function () {
    DailyTimeRecord::factory()
        ->for($this->employee)
        ->forDate(now()->subDays(1))
        ->create();

    $response = $this->actingAs($this->user)
        ->get("{$this->baseUrl}/time-attendance/dtr/{$this->employee->id}/export");

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('rejects unauthorized users', function () {
    Gate::define('can-manage-employees', fn () => false);

    $response = $this->actingAs($this->user)
        ->get("{$this->baseUrl}/time-attendance/dtr/{$this->employee->id}/export?format=xlsx");

    $response->assertForbidden();
});

it('applies date filtering to exported records', function () {
    DailyTimeRecord::factory()
        ->for($this->employee)
        ->forDate('2025-06-15')
        ->create();

    DailyTimeRecord::factory()
        ->for($this->employee)
        ->forDate('2025-05-01')
        ->create();

    $response = $this->actingAs($this->user)
        ->get("{$this->baseUrl}/time-attendance/dtr/{$this->employee->id}/export?format=xlsx&date_from=2025-06-01&date_to=2025-06-30");

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
