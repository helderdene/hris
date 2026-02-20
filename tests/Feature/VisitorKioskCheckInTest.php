<?php

use App\Enums\VisitStatus;
use App\Models\Kiosk;
use App\Models\Tenant;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

describe('Kiosk Visitor Check-In', function () {
    it('checks in a visitor via kiosk QR scan', function () {
        Notification::fake();

        $kiosk = Kiosk::factory()->active()->create();
        $visit = VisitorVisit::factory()->approved()->create([
            'qr_token' => 'valid-qr-token-for-checkin',
        ]);

        $response = $this->postJson(
            "{$this->baseUrl}/kiosk/{$kiosk->token}/visitor-check-in",
            ['qr_token' => 'valid-qr-token-for-checkin']
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedIn);
        expect($visit->checked_in_at)->not->toBeNull();
    });

    it('checks out a visitor on re-scan', function () {
        Notification::fake();

        $kiosk = Kiosk::factory()->active()->create();
        $visit = VisitorVisit::factory()->checkedIn()->create([
            'qr_token' => 'rescan-qr-token',
        ]);

        $response = $this->postJson(
            "{$this->baseUrl}/kiosk/{$kiosk->token}/visitor-check-in",
            ['qr_token' => 'rescan-qr-token']
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedOut);
        expect($visit->checked_out_at)->not->toBeNull();
    });

    it('returns error for invalid QR token', function () {
        $kiosk = Kiosk::factory()->active()->create();

        $response = $this->postJson(
            "{$this->baseUrl}/kiosk/{$kiosk->token}/visitor-check-in",
            ['qr_token' => 'nonexistent-qr-token']
        );

        $response->assertUnprocessable();
    });

    it('returns 404 for invalid kiosk token', function () {
        $response = $this->postJson(
            "{$this->baseUrl}/kiosk/invalid-token-that-does-not-exist/visitor-check-in",
            ['qr_token' => 'some-qr-token']
        );

        $response->assertNotFound();
    });

    it('explicit check-out via visitor-check-out endpoint', function () {
        Notification::fake();

        $kiosk = Kiosk::factory()->active()->create();
        $visit = VisitorVisit::factory()->checkedIn()->create([
            'qr_token' => 'checkout-qr-token',
        ]);

        $response = $this->postJson(
            "{$this->baseUrl}/kiosk/{$kiosk->token}/visitor-check-out",
            ['qr_token' => 'checkout-qr-token']
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedOut);
        expect($visit->checked_out_at)->not->toBeNull();
    });
});
