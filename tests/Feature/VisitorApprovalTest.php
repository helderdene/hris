<?php

use App\Enums\TenantUserRole;
use App\Enums\VisitStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Notifications\VisitorApproved;
use App\Notifications\VisitorRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $plan = Plan::factory()->starter()->create();
    $this->tenant = Tenant::factory()->withPlan($plan)->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
    $this->withoutVite();

    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-organization', fn () => true);
});

describe('Visitor Approval Workflow', function () {
    it('admin approves a pending visit but stays pending until host approves', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertSuccessful();

        $visit->refresh();
        // Still PendingApproval because host hasn't approved yet
        expect($visit->status)->toBe(VisitStatus::PendingApproval);
        expect($visit->approved_at)->not->toBeNull();
        expect($visit->qr_token)->toBeNull();
    });

    it('admin approves a host-approved visit and finalizes approval', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->hostApproved()
            ->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Approved);
        expect($visit->approved_at)->not->toBeNull();
        expect($visit->host_approved_at)->not->toBeNull();
        expect($visit->qr_token)->not->toBeNull();
    });

    it('rejects a pending visit with reason', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/reject", [
                'reason' => 'Visit not allowed at this time.',
            ]);

        $response->assertSuccessful();

        $visit->refresh();
        expect($visit->status)->toBe(VisitStatus::Rejected);
        expect($visit->rejection_reason)->toBe('Visit not allowed at this time.');
    });

    it('sends approval notification to visitor only when fully approved', function () {
        Notification::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->hostApproved()
            ->create(['visitor_id' => $visitor->id]);

        $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        Notification::assertSentOnDemand(VisitorApproved::class);
    });

    it('does not send approval notification when only admin approves', function () {
        Notification::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['visitor_id' => $visitor->id]);

        $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        Notification::assertNothingSentTo($visitor);
    });

    it('sends rejection notification to visitor', function () {
        Notification::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['visitor_id' => $visitor->id]);

        $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/reject", [
                'reason' => 'Security concern.',
            ]);

        Notification::assertSentOnDemand(VisitorRejected::class);
    });

    it('cannot approve an already approved visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->approved()
            ->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/approve");

        $response->assertUnprocessable();
    });

    it('cannot reject an already rejected visit', function () {
        Notification::fake();

        $visit = VisitorVisit::factory()
            ->rejected()
            ->create();

        $response = $this->actingAs($this->admin)
            ->postJson("{$this->baseUrl}/api/visitor-visits/{$visit->id}/reject", [
                'reason' => 'Another reason.',
            ]);

        $response->assertUnprocessable();
    });
});
