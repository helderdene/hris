<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    $this->tenant = Tenant::factory()->create(['slug' => 'demo']);
    app()->instance('tenant', $this->tenant);
});

it('redirects employees to my dashboard from tenant home', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get('http://demo.kasamahr.test/');

    $response->assertRedirect('/my/dashboard');
});

it('redirects employees to my dashboard from /dashboard route', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get('http://demo.kasamahr.test/dashboard');

    $response->assertRedirect('/my/dashboard');
});

it('shows admin dashboard for HR staff on tenant home', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrStaff->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get('http://demo.kasamahr.test/');

    $response->assertSuccessful();
});
