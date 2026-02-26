<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    app()->instance('tenant', $this->tenant);

    // Configure tenant database connection for tests
    $dbManager = new \App\Services\Tenant\TenantDatabaseManager;
    $dbManager->switchConnection($this->tenant);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    Employee::factory()->create(['user_id' => $this->user->id]);
});

it('loads all dashboard props eagerly on /dashboard', function () {
    $this->actingAs($this->user)
        ->get('http://acme.kasamahr.test/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TenantDashboard')
            ->has('pendingActions')
            ->has('priorityItems')
            ->has('notifications')
            ->has('unreadNotificationCount')
            ->has('activityFeed')
            ->has('pendingLeaveDetails')
            ->has('pendingRequisitionDetails')
        );
});

it('loads all dashboard props eagerly on /', function () {
    $this->actingAs($this->user)
        ->get('http://acme.kasamahr.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('TenantDashboard')
            ->has('pendingActions')
            ->has('priorityItems')
            ->has('notifications')
            ->has('unreadNotificationCount')
            ->has('activityFeed')
            ->has('pendingLeaveDetails')
            ->has('pendingRequisitionDetails')
        );
});
