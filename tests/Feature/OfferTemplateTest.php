<?php

use App\Enums\TenantUserRole;
use App\Models\OfferTemplate;
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
    Gate::define('can-manage-organization', fn () => true);
});

it('can list offer templates', function () {
    OfferTemplate::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->getJson("{$this->baseUrl}/api/offer-templates")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('can create an offer template', function () {
    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offer-templates", [
            'name' => 'Standard Offer',
            'content' => '<p>Dear {{candidate_name}}, welcome!</p>',
            'is_default' => false,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(OfferTemplate::where('name', 'Standard Offer')->exists())->toBeTrue();
});

it('can update an offer template', function () {
    $template = OfferTemplate::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)
        ->putJson("{$this->baseUrl}/api/offer-templates/{$template->id}", [
            'name' => 'Updated Name',
            'content' => '<p>Updated content</p>',
        ])
        ->assertRedirect();

    expect($template->fresh()->name)->toBe('Updated Name');
});

it('can delete an offer template', function () {
    $template = OfferTemplate::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("{$this->baseUrl}/api/offer-templates/{$template->id}")
        ->assertRedirect();

    expect(OfferTemplate::withTrashed()->find($template->id)->trashed())->toBeTrue();
});
