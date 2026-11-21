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
