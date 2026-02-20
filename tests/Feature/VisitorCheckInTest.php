<?php

use App\Enums\CheckInMethod;
use App\Enums\TenantUserRole;
use App\Enums\VisitStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
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

    $plan = Plan::factory()->starter()->create();
    $this->tenant = Tenant::factory()->withTrial()->create(['plan_id' => $plan->id]);
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

describe('Manual Visitor Check-In', function () {
    it('checks in an approved visit manually', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->approved()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in"
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedIn);
        expect($visit->check_in_method)->toBe(CheckInMethod::Manual);
        expect($visit->checked_in_at)->not->toBeNull();
    });

    it('checks in with a badge number', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->approved()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in",
            ['badge_number' => 'VB-001']
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->badge_number)->toBe('VB-001');
    });

    it('checks in a pre-registered visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->preRegistered()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in"
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedIn);
    });

    it('cannot check in a pending visit', function () {
        $visit = VisitorVisit::factory()->pendingApproval()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in"
        );

        $response->assertUnprocessable();
    });

    it('cannot check in an already checked-in visit', function () {
        $visit = VisitorVisit::factory()->checkedIn()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-in"
        );

        $response->assertUnprocessable();
    });

    it('checks out a checked-in visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()->checkedIn()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-out"
        );

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::CheckedOut);
        expect($visit->checked_out_at)->not->toBeNull();
    });

    it('cannot check out a non-checked-in visit', function () {
        $visit = VisitorVisit::factory()->approved()->create();

        $response = $this->actingAs($this->admin)->postJson(
            "{$this->baseUrl}/api/visitor-visits/{$visit->id}/check-out"
        );

        $response->assertUnprocessable();
    });
});
