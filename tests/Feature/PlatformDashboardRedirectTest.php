<?php

use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

it('redirects a single-tenant user from the platform dashboard to their tenant subdomain', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $user->tenants()->attach($tenant, ['role' => 'admin']);

    $response = $this->actingAs($user)->get('http://kasamahr.test/dashboard');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('acme.kasamahr.test')
        ->toContain('token=');
    expect(TenantRedirectToken::count())->toBe(1);
});

it('redirects a multi-tenant user from the platform dashboard to the tenant selector', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->tenants()->attach(Tenant::factory()->create(['slug' => 'acme']), ['role' => 'admin']);
    $user->tenants()->attach(Tenant::factory()->create(['slug' => 'globex']), ['role' => 'employee']);

    $this->actingAs($user)
        ->get('http://kasamahr.test/dashboard')
        ->assertRedirect('/select-tenant');
});

it('redirects a user without tenants from the platform dashboard to tenant registration', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get('http://kasamahr.test/dashboard')
        ->assertRedirect(route('tenant.register'));
});

it('redirects a super admin from the platform dashboard to the admin dashboard', function () {
    $user = User::factory()->withoutTwoFactor()->superAdmin()->create();

    $this->actingAs($user)
        ->get('http://kasamahr.test/dashboard')
        ->assertRedirect('/admin');
});
