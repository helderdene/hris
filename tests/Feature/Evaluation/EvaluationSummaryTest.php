<?php

use App\Enums\EvaluationReviewerStatus;
use App\Enums\EvaluationStatus;
use App\Enums\TenantUserRole;
use App\Models\EvaluationCompetencyRating;
use App\Models\EvaluationResponse;
use App\Models\EvaluationReviewer;
use App\Models\EvaluationSummary;
use App\Models\PerformanceCycleParticipant;
use App\Models\PositionCompetency;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForEvaluationSummary(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForEvaluationSummary(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('EvaluationSummary Model', function () {
    it('creates an evaluation summary with correct attributes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();

        $summary = EvaluationSummary::create([
            'performance_cycle_participant_id' => $participant->id,
            'self_competency_avg' => 4.2,
            'manager_competency_avg' => 3.8,
            'peer_competency_avg' => 4.0,
            'overall_competency_avg' => 4.0,
            'kpi_achievement_score' => 95.5,
        ]);

        expect($summary)->toBeInstanceOf(EvaluationSummary::class)
            ->and($summary->self_competency_avg)->toBe('4.20')
            ->and($summary->manager_competency_avg)->toBe('3.80')
            ->and($summary->kpi_achievement_score)->toBe('95.50');
    });

    it('belongs to a participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();
        $summary = EvaluationSummary::factory()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        expect($summary->participant)->toBeInstanceOf(PerformanceCycleParticipant::class)
            ->and($summary->participant->id)->toBe($participant->id);
    });

    it('can be calibrated', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $admin = createTenantUserForEvaluationSummary($tenant, TenantUserRole::Admin);
        $summary = EvaluationSummary::factory()->create();

        $summary->calibrate([
            'final_competency_score' => 4.0,
            'final_kpi_score' => 92.0,
            'final_overall_score' => 4.1,
            'final_rating' => 'exceeds_expectations',
            'calibration_notes' => 'Strong performer',
        ], $admin->id);

        $summary->refresh();

        expect($summary->final_competency_score)->toBe('4.00')
            ->and($summary->final_kpi_score)->toBe('92.00')
            ->and($summary->final_overall_score)->toBe('4.10')
            ->and($summary->final_rating)->toBe('exceeds_expectations')
            ->and($summary->calibration_notes)->toBe('Strong performer')
            ->and($summary->calibrated_at)->not->toBeNull()
            ->and($summary->calibrated_by)->toBe($admin->id);
    });

    it('can be acknowledged by employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $summary = EvaluationSummary::factory()->calibrated()->create();

        $summary->acknowledge('I agree with the assessment');

        expect($summary->employee_acknowledged_at)->not->toBeNull()
            ->and($summary->employee_comments)->toBe('I agree with the assessment');
    });
});

describe('EvaluationService - Summary Calculation', function () {
    it('calculates competency averages by reviewer type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();

        // Create competencies
        $competency1 = PositionCompetency::factory()->create();
        $competency2 = PositionCompetency::factory()->create();

        // Create self reviewer with ratings
        $selfReviewer = EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        $selfResponse = EvaluationResponse::factory()->submitted()->create([
            'evaluation_reviewer_id' => $selfReviewer->id,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $selfResponse->id,
            'position_competency_id' => $competency1->id,
            'rating' => 4,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $selfResponse->id,
            'position_competency_id' => $competency2->id,
            'rating' => 5,
        ]);

        // Create manager reviewer with ratings
        $managerReviewer = EvaluationReviewer::factory()->manager()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        $managerResponse = EvaluationResponse::factory()->submitted()->create([
            'evaluation_reviewer_id' => $managerReviewer->id,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $managerResponse->id,
            'position_competency_id' => $competency1->id,
            'rating' => 3,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $managerResponse->id,
            'position_competency_id' => $competency2->id,
            'rating' => 4,
        ]);

        $service = app(EvaluationService::class);
        $averages = $service->calculateCompetencyAverages($participant);

        expect($averages['self'])->toBe(4.5) // (4 + 5) / 2
            ->and($averages['manager'])->toBe(3.5); // (3 + 4) / 2
    });

    it('generates summary with calculated averages', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();

        // Create competency
        $competency = PositionCompetency::factory()->create();

        // Create self reviewer with ratings
        $selfReviewer = EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        $selfResponse = EvaluationResponse::factory()->submitted()->create([
            'evaluation_reviewer_id' => $selfReviewer->id,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $selfResponse->id,
            'position_competency_id' => $competency->id,
            'rating' => 4,
        ]);

        // Create 2 peer reviewers
        for ($i = 0; $i < 2; $i++) {
            $peerReviewer = EvaluationReviewer::factory()->peer()->submitted()->create([
                'performance_cycle_participant_id' => $participant->id,
            ]);
            $peerResponse = EvaluationResponse::factory()->submitted()->create([
                'evaluation_reviewer_id' => $peerReviewer->id,
            ]);
            EvaluationCompetencyRating::create([
                'evaluation_response_id' => $peerResponse->id,
                'position_competency_id' => $competency->id,
                'rating' => 3 + $i, // 3 and 4
            ]);
        }

        $service = app(EvaluationService::class);
        $summary = $service->generateSummary($participant);

        expect($summary)->toBeInstanceOf(EvaluationSummary::class)
            ->and($summary->self_competency_avg)->toBe(4.0)
            ->and($summary->peer_competency_avg)->toBe(3.5); // (3 + 4) / 2
    });

    it('updates participant evaluation status based on reviewer progress', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create([
            'evaluation_status' => EvaluationStatus::NotStarted,
        ]);

        // Add self reviewer in progress
        EvaluationReviewer::factory()->self()->inProgress()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $service = app(EvaluationService::class);
        $service->updateParticipantEvaluationStatus($participant);

        $participant->refresh();
        expect($participant->evaluation_status)->toBe(EvaluationStatus::SelfInProgress);
    });
});

describe('Evaluation Summary API', function () {
    it('gets summary for a participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $admin = createTenantUserForEvaluationSummary($tenant, TenantUserRole::Admin);
        $participant = PerformanceCycleParticipant::factory()->create();
        EvaluationSummary::factory()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/organization/participants/{$participant->id}/summary");

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'self_competency_avg',
                    'manager_competency_avg',
                    'peer_competency_avg',
                    'overall_competency_avg',
                    'kpi_achievement_score',
                    'final_rating',
                ],
            ]);
    });

    it('calibrates evaluation summary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $admin = createTenantUserForEvaluationSummary($tenant, TenantUserRole::Admin);
        $participant = PerformanceCycleParticipant::factory()->create();
        EvaluationSummary::factory()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/organization/participants/{$participant->id}/summary/calibrate", [
                'final_competency_score' => 4.2,
                'final_kpi_score' => 95.0,
                'final_overall_score' => 4.3,
                'final_rating' => 'exceeds_expectations',
                'calibration_notes' => 'Strong performer this year',
            ]);

        $response->assertSuccessful();

        $summary = EvaluationSummary::where('performance_cycle_participant_id', $participant->id)->first();
        expect($summary->final_rating)->toBe('exceeds_expectations')
            ->and($summary->calibrated_at)->not->toBeNull();
    });

    it('recalculates summary averages', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $admin = createTenantUserForEvaluationSummary($tenant, TenantUserRole::Admin);
        $participant = PerformanceCycleParticipant::factory()->create();

        // Create competency
        $competency = PositionCompetency::factory()->create();

        // Create submitted reviewer with ratings
        $reviewer = EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        $evalResponse = EvaluationResponse::factory()->submitted()->create([
            'evaluation_reviewer_id' => $reviewer->id,
        ]);
        EvaluationCompetencyRating::create([
            'evaluation_response_id' => $evalResponse->id,
            'position_competency_id' => $competency->id,
            'rating' => 5,
        ]);

        EvaluationSummary::factory()->create([
            'performance_cycle_participant_id' => $participant->id,
            'self_competency_avg' => 3.0, // Old value
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/organization/participants/{$participant->id}/summary/recalculate");

        $response->assertSuccessful();

        $summary = EvaluationSummary::where('performance_cycle_participant_id', $participant->id)->first();
        expect($summary->self_competency_avg)->toBe(5.0); // Updated value
    });
});

describe('Evaluation Status Transitions', function () {
    it('transitions from not_started to self_in_progress when self review starts', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create([
            'evaluation_status' => EvaluationStatus::NotStarted,
        ]);

        $selfReviewer = EvaluationReviewer::factory()->self()->create([
            'performance_cycle_participant_id' => $participant->id,
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $selfReviewer->start();

        $service = app(EvaluationService::class);
        $service->updateParticipantEvaluationStatus($participant);

        $participant->refresh();
        expect($participant->evaluation_status)->toBe(EvaluationStatus::SelfInProgress);
    });

    it('transitions to awaiting_reviewers when self review is submitted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create([
            'evaluation_status' => EvaluationStatus::SelfInProgress,
        ]);

        EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        // Add pending peer reviewers
        EvaluationReviewer::factory()->peer()->count(3)->create([
            'performance_cycle_participant_id' => $participant->id,
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $service = app(EvaluationService::class);
        $service->updateParticipantEvaluationStatus($participant);

        $participant->refresh();
        expect($participant->evaluation_status)->toBe(EvaluationStatus::AwaitingReviewers);
    });

    it('transitions to reviewing when reviewers start submitting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create([
            'evaluation_status' => EvaluationStatus::AwaitingReviewers,
        ]);

        EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        // Mix of submitted and pending peer reviewers
        EvaluationReviewer::factory()->peer()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        EvaluationReviewer::factory()->peer()->inProgress()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $service = app(EvaluationService::class);
        $service->updateParticipantEvaluationStatus($participant);

        $participant->refresh();
        expect($participant->evaluation_status)->toBe(EvaluationStatus::Reviewing);
    });

    it('transitions to calibration when all reviewers have submitted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationSummary($tenant);

        $participant = PerformanceCycleParticipant::factory()->create([
            'evaluation_status' => EvaluationStatus::Reviewing,
        ]);

        // All reviewers submitted
        EvaluationReviewer::factory()->self()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        EvaluationReviewer::factory()->manager()->submitted()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);
        EvaluationReviewer::factory()->peer()->submitted()->count(3)->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $service = app(EvaluationService::class);
        $service->updateParticipantEvaluationStatus($participant);

        $participant->refresh();
        expect($participant->evaluation_status)->toBe(EvaluationStatus::Calibration);
    });
});
