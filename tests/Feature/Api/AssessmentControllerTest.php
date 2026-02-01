<?php

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentType;
use App\Enums\TenantUserRole;
use App\Models\Assessment;
use App\Models\JobApplication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    Gate::define('can-manage-organization', fn () => true);
});

it('can list assessments for an application', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();
    Assessment::factory()->count(3)->create(['job_application_id' => $application->id]);

    $this->actingAs($this->user)
        ->getJson("{$this->baseUrl}/api/applications/{$application->id}/assessments")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('can create an assessment for an application at assessment stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/assessments", [
            'test_name' => 'PHP Technical Test',
            'type' => AssessmentType::Technical->value,
            'score' => 85,
            'max_score' => 100,
            'passed' => true,
            'assessed_at' => now()->format('Y-m-d'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.test_name', 'PHP Technical Test')
        ->assertJsonPath('data.type', 'technical')
        ->assertJsonPath('data.passed', true);
});

it('blocks assessment creation before assessment stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Interview)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/assessments", [
            'test_name' => 'PHP Test',
            'type' => AssessmentType::Technical->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('can update an assessment', function () {
    $assessment = Assessment::factory()->create();

    $this->actingAs($this->user)
        ->putJson("{$this->baseUrl}/api/assessments/{$assessment->id}", [
            'test_name' => 'Updated Test Name',
            'score' => 95,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.test_name', 'Updated Test Name');
});

it('can delete an assessment', function () {
    $assessment = Assessment::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("{$this->baseUrl}/api/assessments/{$assessment->id}")
        ->assertNoContent();

    expect(Assessment::find($assessment->id))->toBeNull();
});

it('validates required fields when creating an assessment', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/assessments", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['test_name', 'type']);
});

it('allows assessments at offer stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/assessments", [
            'test_name' => 'Final Assessment',
            'type' => AssessmentType::Aptitude->value,
        ])
        ->assertCreated();
});
