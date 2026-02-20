<?php

use App\Enums\EvaluationReviewerStatus;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\EvaluationCompetencyRating;
use App\Models\EvaluationResponse;
use App\Models\EvaluationReviewer;
use App\Models\PositionCompetency;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

describe('EvaluationResponse Model', function () {
    it('creates an evaluation response with correct attributes', function () {
        $tenant = $this->tenant;

        $reviewer = EvaluationReviewer::factory()->create();

        $response = EvaluationResponse::create([
            'evaluation_reviewer_id' => $reviewer->id,
            'strengths' => 'Strong communication skills',
            'areas_for_improvement' => 'Time management',
            'overall_comments' => 'Good performance overall',
            'development_suggestions' => 'Consider leadership training',
            'is_draft' => true,
            'last_saved_at' => now(),
        ]);

        expect($response)->toBeInstanceOf(EvaluationResponse::class)
            ->and($response->strengths)->toBe('Strong communication skills')
            ->and($response->is_draft)->toBeTrue();
    });

    it('belongs to an evaluation reviewer', function () {
        $tenant = $this->tenant;

        $reviewer = EvaluationReviewer::factory()->create();
        $response = EvaluationResponse::factory()->create([
            'evaluation_reviewer_id' => $reviewer->id,
        ]);

        expect($response->evaluationReviewer)->toBeInstanceOf(EvaluationReviewer::class)
            ->and($response->evaluationReviewer->id)->toBe($reviewer->id);
    });

    it('has many competency ratings', function () {
        $tenant = $this->tenant;

        $response = EvaluationResponse::factory()->create();

        $competency1 = PositionCompetency::factory()->create();
        $competency2 = PositionCompetency::factory()->create();

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competency1->id,
            'rating' => 4,
            'comments' => 'Good performance',
        ]);

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competency2->id,
            'rating' => 3,
            'comments' => 'Average performance',
        ]);

        expect($response->competencyRatings)->toHaveCount(2);
    });

    it('can save as draft', function () {
        $tenant = $this->tenant;

        $response = EvaluationResponse::factory()->create([
            'is_draft' => true,
            'last_saved_at' => null,
        ]);

        $response->saveDraft([
            'strengths' => 'Updated strengths',
        ]);

        expect($response->is_draft)->toBeTrue()
            ->and($response->last_saved_at)->not->toBeNull()
            ->and($response->strengths)->toBe('Updated strengths');
    });

    it('can be submitted', function () {
        $tenant = $this->tenant;

        $response = EvaluationResponse::factory()->draft()->create();

        $response->submit();

        expect($response->is_draft)->toBeFalse()
            ->and($response->submitted_at)->not->toBeNull();
    });

    it('calculates average competency rating', function () {
        $tenant = $this->tenant;

        $response = EvaluationResponse::factory()->create();

        // Create 4 competency ratings
        $competencies = PositionCompetency::factory()->count(4)->create();

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competencies[0]->id,
            'rating' => 4,
        ]);

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competencies[1]->id,
            'rating' => 3,
        ]);

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competencies[2]->id,
            'rating' => 5,
        ]);

        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $response->id,
            'position_competency_id' => $competencies[3]->id,
            'rating' => 4,
        ]);

        // Average should be (4 + 3 + 5 + 4) / 4 = 4.0
        expect($response->getAverageCompetencyRating())->toBe(4.0);
    });
});

describe('EvaluationService - Response Management', function () {
    it('saves evaluation draft', function () {
        $tenant = $this->tenant;

        $reviewer = EvaluationReviewer::factory()->inProgress()->create();

        $service = app(EvaluationService::class);
        $response = $service->saveEvaluationDraft($reviewer, [
            'strengths' => 'Great teamwork',
            'areas_for_improvement' => 'Communication',
            'overall_comments' => 'Good year',
            'development_suggestions' => 'Take a course',
            'competency_ratings' => [],
        ]);

        expect($response)->toBeInstanceOf(EvaluationResponse::class)
            ->and($response->strengths)->toBe('Great teamwork')
            ->and($response->is_draft)->toBeTrue();
    });

    it('submits evaluation and updates reviewer status', function () {
        $tenant = $this->tenant;

        $reviewer = EvaluationReviewer::factory()->inProgress()->create();
        $response = EvaluationResponse::factory()->draft()->create([
            'evaluation_reviewer_id' => $reviewer->id,
        ]);

        $service = app(EvaluationService::class);
        $service->submitEvaluation($reviewer);

        $reviewer->refresh();
        $response->refresh();

        expect($reviewer->status)->toBe(EvaluationReviewerStatus::Submitted)
            ->and($response->is_draft)->toBeFalse()
            ->and($response->submitted_at)->not->toBeNull();
    });

    it('declines review and updates status', function () {
        $tenant = $this->tenant;

        $reviewer = EvaluationReviewer::factory()->create([
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $service = app(EvaluationService::class);
        $service->declineReview($reviewer, 'I have a conflict of interest');

        $reviewer->refresh();

        expect($reviewer->status)->toBe(EvaluationReviewerStatus::Declined)
            ->and($reviewer->decline_reason)->toBe('I have a conflict of interest');
    });
});

describe('Evaluation Response API', function () {
    it('gets response for a reviewer', function () {
        $tenant = $this->tenant;

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $reviewer = EvaluationReviewer::factory()->inProgress()->create([
            'reviewer_employee_id' => $employee->id,
        ]);
        EvaluationResponse::factory()->create([
            'evaluation_reviewer_id' => $reviewer->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("{$this->baseUrl}/api/evaluation-reviewers/{$reviewer->id}/response");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'reviewer',
                'participant',
                'competencies',
                'response' => [
                    'id',
                    'strengths',
                    'areas_for_improvement',
                    'overall_comments',
                    'development_suggestions',
                    'is_draft',
                ],
            ]);
    });

    it('saves response draft', function () {
        $tenant = $this->tenant;

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $reviewer = EvaluationReviewer::factory()->create([
            'reviewer_employee_id' => $employee->id,
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $response = $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/evaluation-reviewers/{$reviewer->id}/response", [
                'strengths' => 'Excellent problem solving',
                'areas_for_improvement' => 'Time management',
                'overall_comments' => 'Great year',
                'development_suggestions' => 'Leadership training',
                'competency_ratings' => [],
                'submit' => false,
            ]);

        $response->assertSuccessful();

        $savedResponse = EvaluationResponse::where('evaluation_reviewer_id', $reviewer->id)->first();
        expect($savedResponse->strengths)->toBe('Excellent problem solving')
            ->and($savedResponse->is_draft)->toBeTrue();
    });

    it('submits response', function () {
        $tenant = $this->tenant;

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $reviewer = EvaluationReviewer::factory()->inProgress()->create([
            'reviewer_employee_id' => $employee->id,
        ]);
        EvaluationResponse::factory()->draft()->create([
            'evaluation_reviewer_id' => $reviewer->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/evaluation-reviewers/{$reviewer->id}/response", [
                'strengths' => 'Updated strengths',
                'areas_for_improvement' => 'Updated areas',
                'overall_comments' => 'Final comments',
                'development_suggestions' => 'Final suggestions',
                'competency_ratings' => [],
                'submit' => true,
            ]);

        $response->assertSuccessful();

        $reviewer->refresh();
        expect($reviewer->status)->toBe(EvaluationReviewerStatus::Submitted);
    });

    it('declines review', function () {
        $tenant = $this->tenant;

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $reviewer = EvaluationReviewer::factory()->create([
            'reviewer_employee_id' => $employee->id,
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $response = $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/evaluation-reviewers/{$reviewer->id}/decline", [
                'reason' => 'I do not work closely with this person',
            ]);

        $response->assertSuccessful();

        $reviewer->refresh();
        expect($reviewer->status)->toBe(EvaluationReviewerStatus::Declined)
            ->and($reviewer->decline_reason)->toBe('I do not work closely with this person');
    });

    it('prevents unauthorized access to other reviewers response', function () {
        $tenant = $this->tenant;

        $user = User::factory()->create();
        $employee1 = Employee::factory()->create(['user_id' => $user->id]);
        $employee2 = Employee::factory()->create();

        $user->tenants()->attach($tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        $reviewer = EvaluationReviewer::factory()->create([
            'reviewer_employee_id' => $employee2->id, // Different employee
        ]);

        $response = $this->actingAs($user)
            ->getJson("{$this->baseUrl}/api/evaluation-reviewers/{$reviewer->id}/response");

        $response->assertForbidden();
    });
});
