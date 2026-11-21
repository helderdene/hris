<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Goal;
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
    $this->base = 'http://demo.kasamahr.test';
});

it('keeps another employee\'s goals and 360 evaluations away from an ordinary employee', function (string $method, string $path) {
    $path = str_replace(['{goal}', '{participant}'], [$this->goal->id, $this->participant->id], $path);

    $this->actingAs($this->user)
        ->json($method, $this->base.$path)
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
]);
