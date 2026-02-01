<?php

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Recruitment\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('allows valid transitions', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Draft)->create([
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);
    $updated = $service->sendOffer($offer);

    expect($updated->status)->toBe(OfferStatus::Sent);
});

it('rejects invalid transitions', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Draft)->create([
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);

    expect(fn () => $service->acceptOffer($offer, [
        'signer_name' => 'Test',
        'signer_email' => 'test@example.com',
        'signature_data' => 'data:image/png;base64,abc',
    ]))->toThrow(ValidationException::class);
});

it('identifies terminal statuses', function () {
    expect(OfferStatus::Accepted->isTerminal())->toBeTrue();
    expect(OfferStatus::Declined->isTerminal())->toBeTrue();
    expect(OfferStatus::Expired->isTerminal())->toBeTrue();
    expect(OfferStatus::Revoked->isTerminal())->toBeTrue();

    expect(OfferStatus::Draft->isTerminal())->toBeFalse();
    expect(OfferStatus::Sent->isTerminal())->toBeFalse();
    expect(OfferStatus::Viewed->isTerminal())->toBeFalse();
    expect(OfferStatus::Pending->isTerminal())->toBeFalse();
});
