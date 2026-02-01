<?php

use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use App\Notifications\OfferSent;
use App\Services\Recruitment\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

it('sends notification when offer is sent', function () {
    Notification::fake();

    $offer = Offer::factory()->withStatus(OfferStatus::Draft)->create([
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);
    $service->sendOffer($offer);

    Notification::assertSentOnDemand(OfferSent::class);
});

it('sends notification when offer is accepted', function () {
    Notification::fake();
    Storage::fake('local');

    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'job_application_id' => $application->id,
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);
    $service->acceptOffer($offer, [
        'signer_name' => 'John Doe',
        'signer_email' => 'john@example.com',
        'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
    ]);

    Notification::assertSentTo($this->user, OfferAccepted::class);
});

it('sends notification when offer is declined', function () {
    Notification::fake();

    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);
    $service->declineOffer($offer, 'Found another job');

    Notification::assertSentTo($this->user, OfferDeclined::class);
});
