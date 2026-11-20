<?php

use App\Enums\OfferStatus;
use App\Enums\TenantUserRole;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Notification::fake();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->base = "http://{$this->tenant->slug}.kasamahr.test";
    $this->offer = Offer::factory()->withStatus(OfferStatus::Sent)->create();

    $this->signed = fn (string $route) => URL::temporarySignedRoute($route, now()->addDay(), [
        'tenant' => $this->tenant->slug,
        'offer' => $this->offer->id,
    ]);

    $this->signature = [
        'signer_name' => 'Candidate Person',
        'signer_email' => 'candidate@example.com',
        'signature_data' => 'data:image/png;base64,AAAA',
    ];
});

it('gives a logged-out candidate signed links to respond with', function () {
    $this->get(($this->signed)('offers.respond'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/OfferResponse')
            ->where('offer.accept_url', fn (string $url) => str_contains($url, "/offers/{$this->offer->id}/respond/accept") && str_contains($url, 'signature='))
            ->where('offer.decline_url', fn (string $url) => str_contains($url, "/offers/{$this->offer->id}/respond/decline") && str_contains($url, 'signature=')));
});

it('lets a logged-out candidate accept through the signed link', function () {
    $this->post(($this->signed)('offers.respond.accept'), $this->signature)->assertRedirect();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::Accepted);
});

it('lets a logged-out candidate decline through the signed link', function () {
    $this->post(($this->signed)('offers.respond.decline'), ['reason' => 'Took another role'])->assertRedirect();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::Declined);
});

it('refuses a response without a valid signature', function () {
    $this->post("{$this->base}/offers/{$this->offer->id}/respond/accept", $this->signature)->assertForbidden();
    $this->post(($this->signed)('offers.respond.accept').'x', $this->signature)->assertForbidden();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::Sent);
});

it('does not let a plain employee accept or decline an offer through the API', function () {
    $employee = User::factory()->create();
    $employee->tenants()->attach($this->tenant->id, ['role' => TenantUserRole::Employee->value, 'invited_at' => now(), 'invitation_accepted_at' => now()]);
    $this->actingAs($employee);

    $this->postJson("{$this->base}/api/offers/{$this->offer->id}/accept", $this->signature)->assertForbidden();
    $this->postJson("{$this->base}/api/offers/{$this->offer->id}/decline", [])->assertForbidden();

    expect($this->offer->fresh()->status)->toBe(OfferStatus::Sent);
});
