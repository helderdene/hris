<?php

use App\Enums\ApplicationStatus;
use App\Enums\ReferenceRecommendation;
use App\Enums\TenantUserRole;
use App\Models\JobApplication;
use App\Models\ReferenceCheck;
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

it('can list reference checks for an application', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();
    ReferenceCheck::factory()->count(2)->create(['job_application_id' => $application->id]);

    $this->actingAs($this->user)
        ->getJson("{$this->baseUrl}/api/applications/{$application->id}/reference-checks")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('can create a reference check at assessment stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/reference-checks", [
            'referee_name' => 'John Smith',
            'referee_email' => 'john@example.com',
            'referee_company' => 'Acme Corp',
            'relationship' => 'Manager',
        ])
        ->assertCreated()
        ->assertJsonPath('data.referee_name', 'John Smith')
        ->assertJsonPath('data.contacted', false);
});

it('blocks reference check creation before assessment stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Interview)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/reference-checks", [
            'referee_name' => 'John Smith',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('can update a reference check', function () {
    $check = ReferenceCheck::factory()->create();

    $this->actingAs($this->user)
        ->putJson("{$this->baseUrl}/api/reference-checks/{$check->id}", [
            'feedback' => 'Excellent candidate, highly recommended.',
            'recommendation' => ReferenceRecommendation::StronglyRecommend->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.recommendation', 'strongly_recommend');
});

it('can delete a reference check', function () {
    $check = ReferenceCheck::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("{$this->baseUrl}/api/reference-checks/{$check->id}")
        ->assertNoContent();

    expect(ReferenceCheck::find($check->id))->toBeNull();
});

it('can mark a reference check as contacted', function () {
    $check = ReferenceCheck::factory()->create(['contacted' => false]);

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/reference-checks/{$check->id}/mark-contacted")
        ->assertSuccessful()
        ->assertJsonPath('data.contacted', true);

    expect($check->fresh()->contacted_at)->not->toBeNull();
});

it('validates required fields when creating a reference check', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/reference-checks", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referee_name']);
});

it('allows reference checks at offer stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/reference-checks", [
            'referee_name' => 'Jane Doe',
        ])
        ->assertCreated();
});
