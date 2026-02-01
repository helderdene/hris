<?php

use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Enums\TenantUserRole;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Recruitment\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
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
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    Gate::define('can-manage-organization', fn () => true);
});

it('can create an offer for a job application', function () {
    $posting = JobPosting::factory()->published()->create();
    $application = JobApplication::factory()->for($posting, 'jobPosting')->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offers", [
            'job_application_id' => $application->id,
            'salary' => 50000,
            'salary_currency' => 'PHP',
            'salary_frequency' => 'monthly',
            'start_date' => now()->addDays(30)->toDateString(),
            'expiry_date' => now()->addDays(14)->toDateString(),
            'position_title' => 'Software Engineer',
            'employment_type' => 'full_time',
        ])
        ->assertRedirect();

    expect(Offer::where('job_application_id', $application->id)->exists())->toBeTrue();
});

it('can send an offer', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Draft)->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offers/{$offer->id}/send")
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Sent);
    expect($offer->fresh()->sent_at)->not->toBeNull();
});

it('can record that an offer was viewed', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'created_by' => $this->user->id,
    ]);

    $service = app(OfferService::class);
    $updated = $service->recordView($offer);

    expect($updated->status)->toBe(OfferStatus::Viewed);
    expect($updated->viewed_at)->not->toBeNull();
});

it('can accept an offer with signature', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'job_application_id' => $application->id,
        'created_by' => $this->user->id,
    ]);

    Storage::fake('local');

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offers/{$offer->id}/accept", [
            'signer_name' => 'John Doe',
            'signer_email' => 'john@example.com',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        ])
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Accepted);
    expect($offer->fresh()->accepted_at)->not->toBeNull();
    expect($offer->fresh()->signatures)->toHaveCount(1);
});

it('can decline an offer with reason', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offers/{$offer->id}/decline", [
            'reason' => 'Found a better opportunity',
        ])
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Declined);
    expect($offer->fresh()->decline_reason)->toBe('Found a better opportunity');
});

it('can revoke an offer', function () {
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/offers/{$offer->id}/revoke", [
            'reason' => 'Position filled internally',
        ])
        ->assertRedirect();

    expect($offer->fresh()->status)->toBe(OfferStatus::Revoked);
    expect($offer->fresh()->revoke_reason)->toBe('Position filled internally');
});

it('transitions application to hired when offer accepted', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
    $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
        'job_application_id' => $application->id,
        'created_by' => $this->user->id,
    ]);

    Storage::fake('local');

    $service = app(OfferService::class);
    $service->acceptOffer($offer, [
        'signer_name' => 'Jane Doe',
        'signer_email' => 'jane@example.com',
        'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
    ]);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Hired);
    expect($application->fresh()->hired_at)->not->toBeNull();
});

it('can download offer pdf', function () {
    Storage::fake('local');

    $pdfPath = 'offer-letters/default/test-offer.pdf';
    $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
        'created_by' => $this->user->id,
        'pdf_path' => $pdfPath,
    ]);

    Storage::disk('local')->put($pdfPath, 'fake-pdf-content');

    $this->actingAs($this->user)
        ->get("{$this->baseUrl}/api/offers/{$offer->id}/pdf")
        ->assertSuccessful();
});
