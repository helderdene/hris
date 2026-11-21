<?php

use App\Enums\TenantUserRole;
use App\Models\CompetencyEvaluation;
use App\Models\Employee;
use App\Models\PerformanceCycleParticipant;
use App\Models\PositionCompetency;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function competencyRatingUser(Tenant $tenant, TenantUserRole $role): array
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

    [$this->report, $this->reportEmployee] = competencyRatingUser($this->tenant, TenantUserRole::Employee);
    [$this->manager, $this->managerEmployee] = competencyRatingUser($this->tenant, TenantUserRole::Supervisor);
    [$this->outsider] = competencyRatingUser($this->tenant, TenantUserRole::Supervisor);

    $this->participant = PerformanceCycleParticipant::factory()->create([
        'employee_id' => $this->reportEmployee->id,
        'manager_id' => $this->managerEmployee->id,
    ]);
    $this->evaluation = CompetencyEvaluation::factory()->create([
        'performance_cycle_participant_id' => $this->participant->id,
        'self_rating' => 2,
        'manager_rating' => null,
    ]);

    $this->base = 'http://demo.kasamahr.test';
    $this->ratingUrl = "{$this->base}/api/performance/competency-evaluations/{$this->evaluation->id}/manager-rating";
});

it('lets the assigned manager rate a report and open their page', function () {
    $this->actingAs($this->manager)
        ->postJson($this->ratingUrl, ['manager_rating' => 4, 'final_rating' => 4])
        ->assertSuccessful();

    expect($this->evaluation->fresh())->manager_rating->toBe(4)->final_rating->toBe(4);

    $this->actingAs($this->manager)
        ->get("{$this->base}/performance/competency-evaluations/participants/{$this->participant->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('canRateSelf', false));
});

it('lets the manager create the first rating but not write the self-rating', function () {
    $competency = PositionCompetency::factory()->create();

    $this->actingAs($this->manager)
        ->postJson("{$this->base}/api/performance/competency-evaluations", [
            'performance_cycle_participant_id' => $this->participant->id,
            'position_competency_id' => $competency->id,
            'manager_rating' => 3,
            'self_rating' => 5,
        ])
        ->assertCreated();

    $created = CompetencyEvaluation::query()->where('position_competency_id', $competency->id)->first();
    expect($created)->manager_rating->toBe(3)->self_rating->toBeNull();
});

it('blocks supervisors who do not manage the participant', function () {
    $this->actingAs($this->outsider)
        ->postJson($this->ratingUrl, ['manager_rating' => 4])
        ->assertForbidden();

    $this->actingAs($this->outsider)
        ->postJson("{$this->base}/api/performance/competency-evaluations", [
            'performance_cycle_participant_id' => $this->participant->id,
            'position_competency_id' => PositionCompetency::factory()->create()->id,
            'manager_rating' => 3,
        ])
        ->assertForbidden();

    $this->actingAs($this->outsider)
        ->get("{$this->base}/performance/competency-evaluations/participants/{$this->participant->id}")
        ->assertForbidden();

    $this->actingAs($this->outsider)
        ->get("{$this->base}/performance/competency-evaluations")
        ->assertForbidden();
});

it('does not let a participant rate themselves as their own manager', function () {
    $this->participant->update(['manager_id' => $this->reportEmployee->id]);

    $this->actingAs($this->report)
        ->postJson($this->ratingUrl, ['manager_rating' => 5])
        ->assertForbidden();
});

it('lists only the manager\'s own reports', function () {
    $other = PerformanceCycleParticipant::factory()->create([
        'performance_cycle_instance_id' => $this->participant->performance_cycle_instance_id,
    ]);
    $instance = $this->participant->performance_cycle_instance_id;

    $this->actingAs($this->manager)
        ->get("{$this->base}/performance/competency-evaluations?instance_id={$instance}")
        ->assertInertia(fn ($page) => $page->has('participants', 1)->where('participants.0.id', $this->participant->id));

    $this->actingAs($this->manager)
        ->get("{$this->base}/performance/competency-evaluations?instance_id={$instance}&participant_id={$other->id}")
        ->assertForbidden();
});
