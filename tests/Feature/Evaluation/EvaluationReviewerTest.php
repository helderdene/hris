<?php

use App\Enums\AssignmentMethod;
use App\Enums\EvaluationReviewerStatus;
use App\Enums\ReviewerType;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvaluationReviewer;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForEvaluationReviewer(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createTenantUserForEvaluationReviewer(Tenant $tenant, TenantUserRole $role): User
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

describe('EvaluationReviewer Model', function () {
    it('creates an evaluation reviewer with correct attributes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();
        $reviewer = Employee::factory()->create();

        $evaluationReviewer = EvaluationReviewer::create([
            'performance_cycle_participant_id' => $participant->id,
            'reviewer_employee_id' => $reviewer->id,
            'reviewer_type' => ReviewerType::Peer,
            'status' => EvaluationReviewerStatus::Pending,
            'assignment_method' => AssignmentMethod::Automatic,
            'invited_at' => now(),
        ]);

        expect($evaluationReviewer)->toBeInstanceOf(EvaluationReviewer::class)
            ->and($evaluationReviewer->reviewer_type)->toBe(ReviewerType::Peer)
            ->and($evaluationReviewer->status)->toBe(EvaluationReviewerStatus::Pending)
            ->and($evaluationReviewer->assignment_method)->toBe(AssignmentMethod::Automatic);
    });

    it('belongs to a participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();
        $evaluationReviewer = EvaluationReviewer::factory()->create([
            'performance_cycle_participant_id' => $participant->id,
        ]);

        expect($evaluationReviewer->participant)->toBeInstanceOf(PerformanceCycleParticipant::class)
            ->and($evaluationReviewer->participant->id)->toBe($participant->id);
    });

    it('belongs to a reviewer employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $reviewer = Employee::factory()->create();
        $evaluationReviewer = EvaluationReviewer::factory()->create([
            'reviewer_employee_id' => $reviewer->id,
        ]);

        expect($evaluationReviewer->reviewerEmployee)->toBeInstanceOf(Employee::class)
            ->and($evaluationReviewer->reviewerEmployee->id)->toBe($reviewer->id);
    });

    it('can start a review', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $evaluationReviewer = EvaluationReviewer::factory()->create([
            'status' => EvaluationReviewerStatus::Pending,
            'started_at' => null,
        ]);

        $evaluationReviewer->start();

        expect($evaluationReviewer->status)->toBe(EvaluationReviewerStatus::InProgress)
            ->and($evaluationReviewer->started_at)->not->toBeNull();
    });

    it('can submit a review', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $evaluationReviewer = EvaluationReviewer::factory()->inProgress()->create();

        $evaluationReviewer->submit();

        expect($evaluationReviewer->status)->toBe(EvaluationReviewerStatus::Submitted)
            ->and($evaluationReviewer->submitted_at)->not->toBeNull();
    });

    it('can decline a review', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $evaluationReviewer = EvaluationReviewer::factory()->create([
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $evaluationReviewer->decline('Too busy with other tasks');

        expect($evaluationReviewer->status)->toBe(EvaluationReviewerStatus::Declined)
            ->and($evaluationReviewer->declined_at)->not->toBeNull()
            ->and($evaluationReviewer->decline_reason)->toBe('Too busy with other tasks');
    });

    it('correctly determines if reviewer can view KPIs', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $selfReviewer = EvaluationReviewer::factory()->self()->create();
        $managerReviewer = EvaluationReviewer::factory()->manager()->create();
        $peerReviewer = EvaluationReviewer::factory()->peer()->create();
        $directReportReviewer = EvaluationReviewer::factory()->directReport()->create();

        expect($selfReviewer->canViewKpis())->toBeTrue()
            ->and($managerReviewer->canViewKpis())->toBeTrue()
            ->and($peerReviewer->canViewKpis())->toBeFalse()
            ->and($directReportReviewer->canViewKpis())->toBeFalse();
    });

    it('scopes by reviewer type', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $participant = PerformanceCycleParticipant::factory()->create();

        EvaluationReviewer::factory()->self()->create(['performance_cycle_participant_id' => $participant->id]);
        EvaluationReviewer::factory()->manager()->create(['performance_cycle_participant_id' => $participant->id]);
        EvaluationReviewer::factory()->peer()->count(3)->create(['performance_cycle_participant_id' => $participant->id]);

        expect(EvaluationReviewer::byType(ReviewerType::Self)->count())->toBe(1)
            ->and(EvaluationReviewer::byType(ReviewerType::Manager)->count())->toBe(1)
            ->and(EvaluationReviewer::byType(ReviewerType::Peer)->count())->toBe(3);
    });

    it('scopes by status', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        EvaluationReviewer::factory()->count(2)->create(['status' => EvaluationReviewerStatus::Pending]);
        EvaluationReviewer::factory()->inProgress()->count(3)->create();
        EvaluationReviewer::factory()->submitted()->count(4)->create();

        expect(EvaluationReviewer::pending()->count())->toBe(2)
            ->and(EvaluationReviewer::submitted()->count())->toBe(4);
    });
});

describe('EvaluationService - Reviewer Assignment', function () {
    it('assigns self reviewer to participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $employee = Employee::factory()->create();
        $participant = PerformanceCycleParticipant::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $service = app(EvaluationService::class);
        $reviewer = $service->assignSelfReviewer($participant);

        expect($reviewer)->toBeInstanceOf(EvaluationReviewer::class)
            ->and($reviewer->reviewer_type)->toBe(ReviewerType::Self)
            ->and($reviewer->reviewer_employee_id)->toBe($employee->id)
            ->and($reviewer->assignment_method)->toBe(AssignmentMethod::Automatic);
    });

    it('assigns manager reviewer when participant has manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $manager = Employee::factory()->create();
        $participant = PerformanceCycleParticipant::factory()->create([
            'manager_id' => $manager->id,
        ]);

        $service = app(EvaluationService::class);
        $reviewer = $service->assignManagerReviewer($participant);

        expect($reviewer)->toBeInstanceOf(EvaluationReviewer::class)
            ->and($reviewer->reviewer_type)->toBe(ReviewerType::Manager)
            ->and($reviewer->reviewer_employee_id)->toBe($manager->id);
    });

    it('returns null when assigning manager reviewer without manager', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $participant = PerformanceCycleParticipant::factory()->withoutManager()->create();

        $service = app(EvaluationService::class);
        $reviewer = $service->assignManagerReviewer($participant);

        expect($reviewer)->toBeNull();
    });

    it('assigns peer reviewers from same department', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        // Create 5 peers in same department
        Employee::factory()->count(5)->create(['department_id' => $department->id]);

        // Create 3 employees in different department (should not be selected)
        $otherDepartment = Department::factory()->create();
        Employee::factory()->count(3)->create(['department_id' => $otherDepartment->id]);

        $participant = PerformanceCycleParticipant::factory()->create([
            'employee_id' => $employee->id,
            'min_peer_reviewers' => 3,
            'max_peer_reviewers' => 5,
        ]);

        $service = app(EvaluationService::class);
        $reviewers = $service->assignPeerReviewers($participant);

        expect($reviewers)->toHaveCount(5);

        foreach ($reviewers as $reviewer) {
            expect($reviewer->reviewer_type)->toBe(ReviewerType::Peer)
                ->and($reviewer->reviewerEmployee->department_id)->toBe($department->id)
                ->and($reviewer->reviewer_employee_id)->not->toBe($employee->id);
        }
    });

    it('does not duplicate reviewers on repeated assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $employee = Employee::factory()->create();
        $participant = PerformanceCycleParticipant::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $service = app(EvaluationService::class);

        // Assign self reviewer twice
        $reviewer1 = $service->assignSelfReviewer($participant);
        $reviewer2 = $service->assignSelfReviewer($participant);

        expect($reviewer1->id)->toBe($reviewer2->id)
            ->and(EvaluationReviewer::where('performance_cycle_participant_id', $participant->id)
                ->where('reviewer_type', ReviewerType::Self)
                ->count())->toBe(1);
    });
});

describe('Evaluation Reviewer API', function () {
    it('lists reviewers for a participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $admin = createTenantUserForEvaluationReviewer($tenant, TenantUserRole::Admin);
        $participant = PerformanceCycleParticipant::factory()->create();

        EvaluationReviewer::factory()->self()->create(['performance_cycle_participant_id' => $participant->id]);
        EvaluationReviewer::factory()->manager()->create(['performance_cycle_participant_id' => $participant->id]);
        EvaluationReviewer::factory()->peer()->count(3)->create(['performance_cycle_participant_id' => $participant->id]);

        $response = $this->actingAs($admin)
            ->getJson("/api/organization/participants/{$participant->id}/reviewers");

        $response->assertSuccessful()
            ->assertJsonCount(5, 'data');
    });

    it('creates a new reviewer for a participant', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $admin = createTenantUserForEvaluationReviewer($tenant, TenantUserRole::Admin);
        $participant = PerformanceCycleParticipant::factory()->create();
        $reviewer = Employee::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/organization/participants/{$participant->id}/reviewers", [
                'reviewer_employee_id' => $reviewer->id,
                'reviewer_type' => 'peer',
            ]);

        $response->assertSuccessful();

        expect(EvaluationReviewer::where('performance_cycle_participant_id', $participant->id)
            ->where('reviewer_employee_id', $reviewer->id)
            ->exists())->toBeTrue();
    });

    it('deletes a reviewer that has not submitted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $admin = createTenantUserForEvaluationReviewer($tenant, TenantUserRole::Admin);
        $evaluationReviewer = EvaluationReviewer::factory()->create([
            'status' => EvaluationReviewerStatus::Pending,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/organization/participants/{$evaluationReviewer->performance_cycle_participant_id}/reviewers/{$evaluationReviewer->id}");

        $response->assertSuccessful();
        expect(EvaluationReviewer::find($evaluationReviewer->id))->toBeNull();
    });

    it('cannot delete a reviewer that has submitted', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForEvaluationReviewer($tenant);

        $admin = createTenantUserForEvaluationReviewer($tenant, TenantUserRole::Admin);
        $evaluationReviewer = EvaluationReviewer::factory()->submitted()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/organization/participants/{$evaluationReviewer->performance_cycle_participant_id}/reviewers/{$evaluationReviewer->id}");

        $response->assertStatus(422);
        expect(EvaluationReviewer::find($evaluationReviewer->id))->not->toBeNull();
    });
});
