<?php

use App\Enums\DevelopmentPlanStatus;
use App\Enums\TenantUserRole;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function developmentPlanUser(Tenant $tenant, TenantUserRole $role): array
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return [$user, Employee::factory()->create(['user_id' => $user->id])];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create(['slug' => 'demo']);
    app()->instance('tenant', $this->tenant);

    [$this->owner, $this->ownerEmployee] = developmentPlanUser($this->tenant, TenantUserRole::Employee);
    [$this->manager, $this->managerEmployee] = developmentPlanUser($this->tenant, TenantUserRole::Supervisor);
    [$this->outsider] = developmentPlanUser($this->tenant, TenantUserRole::Supervisor);
    [$this->hr] = developmentPlanUser($this->tenant, TenantUserRole::HrManager);

    $this->plan = DevelopmentPlan::factory()->pendingApproval()->create([
        'employee_id' => $this->ownerEmployee->id,
        'manager_id' => $this->managerEmployee->id,
    ]);

    $this->base = 'http://demo.kasamahr.test';
});

it('lets the assigned manager and HR open and approve a plan', function (string $who) {
    $this->actingAs($this->{$who})
        ->get("{$this->base}/performance/development-plans/{$this->plan->id}")
        ->assertSuccessful();

    $this->actingAs($this->{$who})
        ->postJson("{$this->base}/api/performance/development-plans/{$this->plan->id}/approve")
        ->assertSuccessful();

    expect($this->plan->fresh()->status)->toBe(DevelopmentPlanStatus::Approved);
})->with(['manager', 'hr']);

it('blocks everyone else from opening, approving or rejecting a plan', function (string $who) {
    $this->actingAs($this->{$who})
        ->get("{$this->base}/performance/development-plans/{$this->plan->id}")
        ->assertForbidden();

    $this->actingAs($this->{$who})
        ->postJson("{$this->base}/api/performance/development-plans/{$this->plan->id}/approve")
        ->assertForbidden();

    $this->actingAs($this->{$who})
        ->postJson("{$this->base}/api/performance/development-plans/{$this->plan->id}/reject")
        ->assertForbidden();

    expect($this->plan->fresh()->status)->toBe(DevelopmentPlanStatus::PendingApproval);
})->with(['owner', 'outsider']);

it('does not let an employee approve a plan by naming themselves as manager', function () {
    $this->plan->update(['manager_id' => $this->ownerEmployee->id]);

    $this->actingAs($this->owner)
        ->postJson("{$this->base}/api/performance/development-plans/{$this->plan->id}/approve")
        ->assertForbidden();
});

it('lists only the plans a manager reviews, and all plans for HR', function () {
    DevelopmentPlan::factory()->pendingApproval()->create();

    $this->actingAs($this->manager)
        ->get("{$this->base}/performance/development-plans")
        ->assertInertia(fn ($page) => $page->has('plans.data', 1)->where('pendingCount', 1));

    $this->actingAs($this->outsider)
        ->get("{$this->base}/performance/development-plans")
        ->assertInertia(fn ($page) => $page->has('plans.data', 0)->where('pendingCount', 0));

    $this->actingAs($this->hr)
        ->get("{$this->base}/performance/development-plans")
        ->assertInertia(fn ($page) => $page->has('plans.data', 2)->where('pendingCount', 2));
});

it('lets the assigned manager return a plan for revision', function () {
    $this->actingAs($this->manager)
        ->postJson("{$this->base}/api/performance/development-plans/{$this->plan->id}/reject", ['notes' => 'More detail'])
        ->assertSuccessful();

    expect($this->plan->fresh()->status)->not->toBe(DevelopmentPlanStatus::PendingApproval);
});

it('ignores an approver chosen by the employee and uses their supervisor', function () {
    $this->ownerEmployee->update(['supervisor_id' => $this->managerEmployee->id]);
    $friend = Employee::factory()->create();

    $this->actingAs($this->owner)
        ->postJson("{$this->base}/api/my/development-plans", ['title' => 'Grow', 'manager_id' => $friend->id])
        ->assertSuccessful();

    $created = DevelopmentPlan::query()->latest('id')->first();
    expect($created->manager_id)->toBe($this->managerEmployee->id);

    $this->actingAs($this->owner)
        ->putJson("{$this->base}/api/my/development-plans/{$created->id}", ['title' => 'Grow', 'manager_id' => $friend->id])
        ->assertSuccessful();

    expect($created->fresh()->manager_id)->toBe($this->managerEmployee->id);
});
