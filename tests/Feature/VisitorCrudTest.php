<?php

use App\Enums\TenantUserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $plan = Plan::factory()->starter()->create();
    $this->tenant = Tenant::factory()->withPlan($plan)->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    // Create admin user
    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    // Create regular employee user (no org management permission)
    $this->employee = User::factory()->create();
    $this->employee->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

describe('Visitor CRUD', function () {
    it('lists visitors for admin users', function () {
        Visitor::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitors");

        $response->assertSuccessful();
        expect($response->json('data'))->toHaveCount(3);
    });

    it('searches visitors by name', function () {
        Visitor::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Visitor::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Visitor::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Reyes']);

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitors?search=Maria");

        $response->assertSuccessful();

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['first_name'])->toBe('Maria');
    });

    it('creates a visitor', function () {
        $data = [
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'email' => 'ana.garcia@example.com',
            'phone' => '09171234567',
            'company' => 'Acme Corp',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitors", $data);

        $response->assertCreated();
        $this->assertDatabaseHas('visitors', [
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'email' => 'ana.garcia@example.com',
        ]);
    });

    it('shows a single visitor', function () {
        $visitor = Visitor::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("{$this->baseUrl}/api/visitors/{$visitor->id}");

        $response->assertSuccessful();
        expect($response->json('first_name'))->toBe('Carlos');
        expect($response->json('last_name'))->toBe('Mendoza');
    });

    it('updates a visitor', function () {
        $visitor = Visitor::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("{$this->baseUrl}/api/visitors/{$visitor->id}", [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
            ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('visitors', [
            'id' => $visitor->id,
            'first_name' => 'New',
            'email' => 'new@example.com',
        ]);
    });

    it('deletes a visitor', function () {
        $visitor = Visitor::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("{$this->baseUrl}/api/visitors/{$visitor->id}");

        $response->assertSuccessful();
        $this->assertDatabaseMissing('visitors', [
            'id' => $visitor->id,
        ]);
    });

    it('forbids non-admin users from accessing visitors', function () {
        Visitor::factory()->count(2)->create();

        $response = $this->actingAs($this->employee)
            ->getJson("{$this->baseUrl}/api/visitors");

        $response->assertForbidden();
    });

    it('validates required fields on create', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitors", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['first_name', 'last_name']);
    });
});
