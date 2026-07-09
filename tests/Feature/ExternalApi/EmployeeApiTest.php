<?php

use App\Models\Employee;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create(['slug' => 'test-company']);
    $this->token = $this->tenant->generateApiToken();
    $this->baseUrl = 'http://kasamahr.test/external-api/v1/test-company';

    app()->instance('tenant', $this->tenant);
});

it('returns 401 without authorization header', function () {
    $this->getJson("{$this->baseUrl}/employees")
        ->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 with invalid token', function () {
    $this->getJson("{$this->baseUrl}/employees", [
        'Authorization' => 'Bearer invalid-token-here',
    ])
        ->assertStatus(401)
        ->assertJson(['message' => 'Invalid API token.']);
});

it('returns 404 for unknown tenant slug', function () {
    $this->getJson('http://kasamahr.test/external-api/v1/nonexistent/employees', [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertStatus(404)
        ->assertJson(['message' => 'Tenant not found.']);
});

it('lists employees with valid token', function () {
    Employee::factory()->count(3)->create();

    $this->getJson("{$this->baseUrl}/employees", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('shows a single employee', function () {

    $employee = Employee::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $this->getJson("{$this->baseUrl}/employees/{$employee->id}", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
});

it('filters employees by employment status', function () {

    Employee::factory()->active()->count(2)->create();
    Employee::factory()->resigned()->create();

    $this->getJson("{$this->baseUrl}/employees?employment_status=active", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('filters employees by updated_since', function () {
    $old = Employee::factory()->create();
    // Use query builder to bypass model events/observers
    Employee::query()->where('id', $old->id)->update(['updated_at' => now()->subDays(10)]);

    $recent = Employee::factory()->create();
    Employee::query()->where('id', $recent->id)->update(['updated_at' => now()]);

    $since = now()->subDays(2)->toISOString();

    $this->getJson("{$this->baseUrl}/employees?updated_since={$since}", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('does not expose sensitive fields in list response', function () {

    Employee::factory()->create();

    $response = $this->getJson("{$this->baseUrl}/employees", [
        'Authorization' => "Bearer {$this->token}",
    ])->assertSuccessful();

    $employee = $response->json('data.0');

    expect($employee)->not->toHaveKeys([
        'basic_salary',
        'pay_frequency',
        'tin',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'address',
        'emergency_contact',
        'kiosk_pin',
        'kiosk_pin_hash',
    ]);
});

it('does not expose sensitive fields in detail response', function () {

    $employee = Employee::factory()->create();

    $response = $this->getJson("{$this->baseUrl}/employees/{$employee->id}", [
        'Authorization' => "Bearer {$this->token}",
    ])->assertSuccessful();

    $data = $response->json();

    expect($data)->not->toHaveKeys([
        'basic_salary',
        'pay_frequency',
        'tin',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'address',
        'emergency_contact',
        'kiosk_pin',
        'kiosk_pin_hash',
    ]);
});

it('records api_token_last_used_at on successful request', function () {

    expect($this->tenant->fresh()->api_token_last_used_at)->toBeNull();

    $this->getJson("{$this->baseUrl}/employees", [
        'Authorization' => "Bearer {$this->token}",
    ])->assertSuccessful();

    expect($this->tenant->fresh()->api_token_last_used_at)->not->toBeNull();
});

it('paginates employee list', function () {

    Employee::factory()->count(5)->create();

    $this->getJson("{$this->baseUrl}/employees?per_page=2", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5);
});

it('filters employees by search query', function () {

    Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
    Employee::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

    $this->getJson("{$this->baseUrl}/employees?search=Maria", [
        'Authorization' => "Bearer {$this->token}",
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['first_name' => 'Maria']);
});
