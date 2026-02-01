<?php

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\TenantUserRole;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Recruitment\JobApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
});

describe('JobApplication Model', function () {
    it('auto-sets applied_at on creation', function () {
        $candidate = Candidate::factory()->create();
        $posting = JobPosting::factory()->published()->create();

        $application = JobApplication::create([
            'candidate_id' => $candidate->id,
            'job_posting_id' => $posting->id,
            'status' => ApplicationStatus::Applied,
            'source' => ApplicationSource::CareersPage,
        ]);

        expect($application->applied_at)->not->toBeNull();
    });

    it('enforces unique candidate-posting constraint', function () {
        $candidate = Candidate::factory()->create();
        $posting = JobPosting::factory()->published()->create();

        JobApplication::factory()->for($candidate, 'candidate')->for($posting, 'jobPosting')->create();

        expect(fn () => JobApplication::factory()->for($candidate, 'candidate')->for($posting, 'jobPosting')->create())
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('filters by posting', function () {
        $posting1 = JobPosting::factory()->published()->create();
        $posting2 = JobPosting::factory()->published()->create();

        JobApplication::factory()->for($posting1, 'jobPosting')->count(3)->create();
        JobApplication::factory()->for($posting2, 'jobPosting')->count(2)->create();

        expect(JobApplication::forPosting($posting1->id)->count())->toBe(3);
    });

    it('filters by status', function () {
        $posting = JobPosting::factory()->published()->create();

        JobApplication::factory()->for($posting, 'jobPosting')->withStatus(ApplicationStatus::Applied)->count(2)->create();
        JobApplication::factory()->for($posting, 'jobPosting')->withStatus(ApplicationStatus::Screening)->create();

        expect(JobApplication::withStatus(ApplicationStatus::Applied)->count())->toBe(2);
    });
});

describe('JobApplication Status Transitions', function () {
    it('transitions from Applied to Screening', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Applied)->create();
        $service = app(JobApplicationService::class);

        $updated = $service->transitionStatus($application, ApplicationStatus::Screening, 'Moving forward');

        expect($updated->status)->toBe(ApplicationStatus::Screening);
        expect($updated->screening_at)->not->toBeNull();
        expect($updated->statusHistories)->toHaveCount(1);
    });

    it('rejects invalid transition', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Applied)->create();
        $service = app(JobApplicationService::class);

        expect(fn () => $service->transitionStatus($application, ApplicationStatus::Hired))
            ->toThrow(ValidationException::class);
    });

    it('cannot transition from terminal status', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $service = app(JobApplicationService::class);

        expect(fn () => $service->transitionStatus($application, ApplicationStatus::Applied))
            ->toThrow(ValidationException::class);
    });

    it('sets rejection reason when rejecting', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Applied)->create();
        $service = app(JobApplicationService::class);

        $updated = $service->transitionStatus($application, ApplicationStatus::Rejected, null, 'Not qualified');

        expect($updated->status)->toBe(ApplicationStatus::Rejected);
        expect($updated->rejection_reason)->toBe('Not qualified');
        expect($updated->rejected_at)->not->toBeNull();
    });
});

describe('JobApplication API', function () {
    it('updates application status via API', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Applied)->create();

        $this->actingAs($user)
            ->patchJson("{$this->baseUrl}/api/applications/{$application->id}/status", [
                'status' => 'screening',
                'notes' => 'Good candidate',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', 'screening');
    });

    it('rejects invalid status transition via API', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Applied)->create();

        $this->actingAs($user)
            ->patchJson("{$this->baseUrl}/api/applications/{$application->id}/status", [
                'status' => 'hired',
            ])
            ->assertUnprocessable();
    });

    it('creates application via API', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create();
        $posting = JobPosting::factory()->published()->create();

        $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/applications", [
                'candidate_id' => $candidate->id,
                'job_posting_id' => $posting->id,
                'source' => 'manual_entry',
            ])
            ->assertCreated();

        expect(JobApplication::where('candidate_id', $candidate->id)->exists())->toBeTrue();
    });
});
