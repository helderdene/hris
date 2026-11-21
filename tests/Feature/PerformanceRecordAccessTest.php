<?php

use App\Enums\TenantUserRole;
use App\Models\CompetencyEvaluation;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\KpiAssignment;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create(['slug' => 'demo']);
    app()->instance('tenant', $this->tenant);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    Employee::factory()->create(['user_id' => $this->user->id]);

    $this->goal = Goal::factory()->create();
    $this->participant = PerformanceCycleParticipant::factory()->create();
    $this->kpi = KpiAssignment::factory()->create();
    $this->competency = CompetencyEvaluation::factory()->create();
    $this->base = 'http://demo.kasamahr.test';
});

it('keeps another employee\'s goals, KPIs and evaluations away from an ordinary employee', function (string $method, string $path) {
    $path = str_replace(
        ['{goal}', '{participant}', '{kpi}', '{competency}'],
        [$this->goal->id, $this->participant->id, $this->kpi->id, $this->competency->id],
        $path,
    );

    $this->actingAs($this->user)
        ->json($method, $this->base.$path, $method === 'POST' ? ['value' => 1] : [])
        ->assertForbidden();
})->with([
    'goals page' => ['GET', '/performance/goals'],
    'goal page' => ['GET', '/performance/goals/{goal}'],
    'goal api' => ['GET', '/api/performance/goals/{goal}'],
    'goal approve' => ['POST', '/api/performance/goals/{goal}/approve'],
    'goal comments' => ['GET', '/api/performance/goals/{goal}/comments'],
    'goal as own' => ['GET', '/api/my/goals/{goal}'],
    'goal as manager' => ['POST', '/api/manager/team-goals/{goal}/approve'],
    'evaluations page' => ['GET', '/performance/evaluations'],
    'evaluation page' => ['GET', '/performance/evaluations/{participant}'],
    'evaluation summary' => ['GET', '/api/organization/participants/{participant}/summary'],
    'evaluation reviewers' => ['GET', '/api/organization/participants/{participant}/reviewers'],
    'own results page' => ['GET', '/my/evaluations/{participant}/results'],
    'kpis page' => ['GET', '/performance/kpis'],
    'kpi templates' => ['GET', '/api/performance/kpi-templates'],
    'kpi assignment' => ['GET', '/api/performance/kpi-assignments/{kpi}'],
    'kpi progress' => ['POST', '/api/performance/kpi-assignments/{kpi}/progress'],
    'kpi complete' => ['POST', '/api/performance/kpi-assignments/{kpi}/complete'],
    'participant kpis' => ['GET', '/api/performance/participants/{participant}/kpis'],
    'competency evaluations page' => ['GET', '/performance/competency-evaluations'],
    'competency evaluation page' => ['GET', '/performance/competency-evaluations/participants/{participant}'],
    'competency evaluation' => ['GET', '/api/performance/competency-evaluations/{competency}'],
    'competency self rating' => ['POST', '/api/performance/competency-evaluations/{competency}/self-rating'],
    'competency manager rating' => ['POST', '/api/performance/competency-evaluations/{competency}/manager-rating'],
    'participant competencies' => ['GET', '/api/performance/participants/{participant}/competency-evaluations'],
    'competencies page' => ['GET', '/organization/competencies'],
    'competency matrix page' => ['GET', '/organization/competency-matrix'],
]);

it('lets HR staff and HR consultants use the performance pages', function (TenantUserRole $role, string $path) {
    $this->user->tenants()->updateExistingPivot($this->tenant->id, ['role' => $role->value]);

    $path = str_replace(
        ['{goal}', '{participant}', '{kpi}', '{competency}'],
        [$this->goal->id, $this->participant->id, $this->kpi->id, $this->competency->id],
        $path,
    );

    $this->actingAs($this->user)
        ->getJson($this->base.$path)
        ->assertSuccessful();
})->with([TenantUserRole::HrStaff, TenantUserRole::HrConsultant])->with([
    '/performance/goals',
    '/performance/goals/{goal}',
    '/performance/kpis',
    '/performance/evaluations',
    '/performance/evaluations/{participant}',
    '/performance/competency-evaluations',
    '/performance/competency-evaluations/participants/{participant}',
    '/performance/development-plans',
    '/performance/analytics',
    '/organization/performance-cycles',
    '/organization/competencies',
    '/organization/competency-matrix',
    '/api/performance/goals/{goal}',
    '/api/performance/kpi-assignments/{kpi}',
    '/api/performance/competency-evaluations/{competency}',
    '/api/organization/participants/{participant}/summary',
]);

it('still keeps HR staff out of organization-only settings', function () {
    $this->user->tenants()->updateExistingPivot($this->tenant->id, ['role' => TenantUserRole::HrStaff->value]);

    $this->actingAs($this->user)
        ->get("{$this->base}/organization/leave-balances")
        ->assertForbidden();
});

it('lets HR staff and HR consultants act on performance records', function (TenantUserRole $role) {
    $this->user->tenants()->updateExistingPivot($this->tenant->id, ['role' => $role->value]);
    $plan = \App\Models\DevelopmentPlan::factory()->pendingApproval()->create();

    $this->actingAs($this->user)
        ->get("{$this->base}/performance/development-plans/{$plan->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('tenant.can_manage_performance', true)
            ->where('tenant.can_manage_organization', false));

    $this->actingAs($this->user)
        ->postJson("{$this->base}/api/performance/development-plans/{$plan->id}/approve")
        ->assertSuccessful();

    $this->actingAs($this->user)
        ->postJson("{$this->base}/api/performance/competency-evaluations/{$this->competency->id}/manager-rating", ['manager_rating' => 4])
        ->assertSuccessful();

    expect($plan->fresh()->status)->toBe(\App\Enums\DevelopmentPlanStatus::Approved)
        ->and($this->competency->fresh()->manager_rating)->toBe(4);
})->with([TenantUserRole::HrStaff, TenantUserRole::HrConsultant]);
