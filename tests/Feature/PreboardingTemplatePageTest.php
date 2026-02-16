<?php

use App\Enums\TenantUserRole;
use App\Models\PreboardingTemplate;
use App\Models\PreboardingTemplateItem;
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
});

/*
|--------------------------------------------------------------------------
| Template Page Controller Tests
|--------------------------------------------------------------------------
*/

it('renders preboarding templates index page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    PreboardingTemplate::factory()->count(3)->create();

    $response = $this->get("{$this->baseUrl}/preboarding-templates");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Preboarding/Templates/Index')
        ->has('templates', 3)
    );
});

it('renders preboarding template create page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/preboarding-templates/create");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Preboarding/Templates/Form')
        ->where('template', null)
        ->has('itemTypes')
        ->has('documentCategories')
    );
});

it('renders preboarding template edit page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $template = PreboardingTemplate::factory()
        ->has(PreboardingTemplateItem::factory()->count(2), 'items')
        ->create();

    $response = $this->get("{$this->baseUrl}/preboarding-templates/{$template->id}/edit");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Preboarding/Templates/Form')
        ->where('template.id', $template->id)
        ->has('template.items', 2)
    );
});

/*
|--------------------------------------------------------------------------
| Authorization Tests
|--------------------------------------------------------------------------
*/

it('requires authentication to access preboarding templates', function () {
    $response = $this->get("{$this->baseUrl}/preboarding-templates");

    $response->assertRedirect();
});

it('requires can-manage-organization permission for preboarding templates', function () {
    $this->withoutVite();
    Gate::define('can-manage-organization', fn () => false);

    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/preboarding-templates");

    $response->assertForbidden();
});
