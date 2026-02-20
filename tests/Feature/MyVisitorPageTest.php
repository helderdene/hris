<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

    // Host employee with user
    $this->hostUser = User::factory()->create();
    $this->hostUser->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $this->hostEmployee = Employee::factory()->create(['user_id' => $this->hostUser->id]);
});

describe('My Visitors Page', function () {
    it('renders the my visitors page for authenticated user', function () {
        $response = $this->actingAs($this->hostUser)
            ->get("{$this->baseUrl}/my/visitors");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page->component('My/Visitors'));
    });

    it('shows only visits where user is the host', function () {
        // Visit where user is host
        $myVisit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        // Visit where someone else is host
        $otherEmployee = Employee::factory()->create();
        $otherVisit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $otherEmployee->id]);

        $response = $this->actingAs($this->hostUser)
            ->get("{$this->baseUrl}/my/visitors");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('My/Visitors')
            ->has('pending', 1)
            ->where('pending.0.id', $myVisit->id)
        );
    });

    it('shows pending visits that host has not yet approved', function () {
        // Pending, not yet host-approved
        VisitorVisit::factory()
            ->pendingApproval()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        // Already host-approved (should be in history, not pending)
        VisitorVisit::factory()
            ->hostApproved()
            ->create(['host_employee_id' => $this->hostEmployee->id]);

        $response = $this->actingAs($this->hostUser)
            ->get("{$this->baseUrl}/my/visitors");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('pending', 1)
            ->where('pendingCount', 1)
        );
    });

    it('denies unauthenticated access', function () {
        $response = $this->get("{$this->baseUrl}/my/visitors");

        $response->assertRedirect();
    });
});
