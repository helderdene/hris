<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('uploads logo to tenant-specific path', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme-corp']);
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $this->actingAs($user)
        ->post(route('tenant.settings.logo.store', ['tenantModel' => $tenant]), [
            'logo' => $file,
        ])
        ->assertRedirect();

    $tenant->refresh();

    // Verify the logo was stored in the correct tenant-specific path
    expect($tenant->logo_path)->not->toBeNull();
    expect($tenant->logo_path)->toContain("tenants/{$tenant->slug}/branding");
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $tenant->logo_path));
});

it('injects tenant context into inertia shared data when on subdomain', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'slug' => 'acme-corp',
        'name' => 'ACME Corporation',
        'primary_color' => '#3b82f6',
        'logo_path' => '/storage/tenants/acme-corp/branding/logo.png',
    ]);
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    // Bind the tenant to the app container (simulating ResolveTenant middleware)
    app()->instance('tenant', $tenant);

    // Request to main domain dashboard (where tenant context is injected via HandleInertiaRequests)
    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('tenant')
        ->has('tenant.id')
        ->has('tenant.name')
        ->has('tenant.slug')
        ->has('tenant.logo_url')
        ->has('tenant.primary_color')
        ->has('tenant.user_role')
        ->where('tenant.name', 'ACME Corporation')
        ->where('tenant.slug', 'acme-corp')
        ->where('tenant.primary_color', '#3b82f6')
        ->where('tenant.logo_url', '/storage/tenants/acme-corp/branding/logo.png')
        ->where('tenant.user_role', 'admin')
    );
});

it('does not inject tenant context on main domain', function () {
    $user = User::factory()->create();

    // Request to main domain dashboard (no tenant context)
    $this->actingAs($user)
        ->withHeaders(['Host' => 'kasamahr.test'])
        ->get('http://kasamahr.test/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('tenant', null)
        );
});

it('validates logo format and size', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme-corp']);
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    // Test invalid format
    $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

    $this->actingAs($user)
        ->post(route('tenant.settings.logo.store', ['tenantModel' => $tenant]), [
            'logo' => $invalidFile,
        ])
        ->assertSessionHasErrors('logo');

    // Test oversized file (over 2MB)
    $oversizedFile = UploadedFile::fake()->image('logo.png')->size(3000);

    $this->actingAs($user)
        ->post(route('tenant.settings.logo.store', ['tenantModel' => $tenant]), [
            'logo' => $oversizedFile,
        ])
        ->assertSessionHasErrors('logo');
});
