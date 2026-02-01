<?php

use App\Enums\KpiAssignmentStatus;
use App\Enums\TenantUserRole;
use App\Http\Requests\StoreKpiAssignmentRequest;
use App\Models\Employee;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KpiAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForKpiAssignment(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForKpiAssignment(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create a performance cycle participant.
 */
function createParticipantForKpiAssignment(Tenant $tenant): PerformanceCycleParticipant
{
    $cycle = PerformanceCycle::factory()->create();
    $instance = PerformanceCycleInstance::factory()->create([
        'performance_cycle_id' => $cycle->id,
    ]);
    $employee = Employee::factory()->create();

    return PerformanceCycleParticipant::factory()->create([
        'performance_cycle_instance_id' => $instance->id,
        'employee_id' => $employee->id,
    ]);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('KpiAssignment API', function () {
    it('creates a KPI assignment via service', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create([
            'metric_unit' => 'PHP',
            'default_target' => 100000,
            'default_weight' => 1.0,
        ]);
        $participant = createParticipantForKpiAssignment($tenant);

        $service = new KpiAssignmentService;
        $assignment = $service->assignKpiToParticipant(
            $template,
            $participant,
            150000,
            2.0,
            'Increased target for senior employee'
        );

        expect($assignment)->toBeInstanceOf(KpiAssignment::class);
        expect((float) $assignment->target_value)->toBe(150000.0);
        expect((float) $assignment->weight)->toBe(2.0);
        expect($assignment->status)->toBe(KpiAssignmentStatus::Pending);

        $this->assertDatabaseHas('kpi_assignments', [
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 150000,
        ]);
    });

    it('validates required fields for assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $rules = (new StoreKpiAssignmentRequest)->rules();
        $validator = Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('kpi_template_id'))->toBeTrue();
        expect($validator->errors()->has('performance_cycle_participant_id'))->toBeTrue();
        expect($validator->errors()->has('target_value'))->toBeTrue();
    });

    it('bulk assigns KPI to multiple participants', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participants = collect([
            createParticipantForKpiAssignment($tenant),
            createParticipantForKpiAssignment($tenant),
            createParticipantForKpiAssignment($tenant),
        ]);

        $service = new KpiAssignmentService;
        $assignments = $service->bulkAssignKpi(
            $template,
            $participants->pluck('id')->toArray(),
            100000,
            1.0
        );

        expect($assignments)->toHaveCount(3);
        expect(KpiAssignment::count())->toBe(3);
    });

    it('records progress for assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        $assignment = KpiAssignment::factory()->pending()->create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 100000,
        ]);

        $service = new KpiAssignmentService;
        $progressEntry = $service->recordProgress($assignment, 50000, 'Mid-month update', $admin);

        expect((float) $progressEntry->value)->toBe(50000.0);
        expect($progressEntry->notes)->toBe('Mid-month update');

        $assignment->refresh();
        expect((float) $assignment->actual_value)->toBe(50000.0);
        expect($assignment->status)->toBe(KpiAssignmentStatus::InProgress);
        expect((float) $assignment->achievement_percentage)->toBe(50.0);

        $this->assertDatabaseHas('kpi_progress_entries', [
            'kpi_assignment_id' => $assignment->id,
            'value' => 50000,
        ]);
    });

    it('calculates achievement percentage correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        $assignment = KpiAssignment::factory()->pending()->create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 100,
        ]);

        $service = new KpiAssignmentService;

        // Record progress of 75 out of 100
        $service->recordProgress($assignment, 75, null, $admin);
        $assignment->refresh();
        expect((float) $assignment->achievement_percentage)->toBe(75.0);

        // Record progress of 100 (100% achievement)
        $service->recordProgress($assignment, 100, null, $admin);
        $assignment->refresh();
        expect((float) $assignment->achievement_percentage)->toBe(100.0);

        // Record progress of 120 (120% over-achievement)
        $service->recordProgress($assignment, 120, null, $admin);
        $assignment->refresh();
        expect((float) $assignment->achievement_percentage)->toBe(120.0);
    });

    it('marks assignment as completed', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        $assignment = KpiAssignment::factory()->inProgress()->create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'actual_value' => 100000,
            'achievement_percentage' => 100,
        ]);

        $service = new KpiAssignmentService;
        $service->markCompleted($assignment);

        $assignment->refresh();
        expect($assignment->status)->toBe(KpiAssignmentStatus::Completed);
        expect($assignment->completed_at)->not->toBeNull();
    });

    it('calculates weighted participant KPI summary', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template1 = KpiTemplate::factory()->create();
        $template2 = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        // KPI 1: 80% achievement, weight 2.0
        KpiAssignment::factory()->create([
            'kpi_template_id' => $template1->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 100,
            'actual_value' => 80,
            'achievement_percentage' => 80,
            'weight' => 2.0,
            'status' => KpiAssignmentStatus::Completed,
        ]);

        // KPI 2: 100% achievement, weight 1.0
        KpiAssignment::factory()->create([
            'kpi_template_id' => $template2->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 50,
            'actual_value' => 50,
            'achievement_percentage' => 100,
            'weight' => 1.0,
            'status' => KpiAssignmentStatus::Completed,
        ]);

        $service = new KpiAssignmentService;
        $summary = $service->getParticipantKpiSummary($participant);

        // Weighted average: (80 * 2 + 100 * 1) / 3 = 260 / 3 = 86.67
        expect($summary['total_kpis'])->toBe(2);
        expect($summary['completed_kpis'])->toBe(2);
        expect($summary['weighted_average_achievement'])->toBe(86.67);
    });

    it('returns progress history in descending order', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        $assignment = KpiAssignment::factory()->create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => 100000,
        ]);

        $service = new KpiAssignmentService;

        // Record progress entries
        $service->recordProgress($assignment, 25000, 'Week 1', $admin);
        $service->recordProgress($assignment, 50000, 'Week 2', $admin);
        $service->recordProgress($assignment, 75000, 'Week 3', $admin);

        $history = $service->getProgressHistory($assignment);

        expect($history)->toHaveCount(3);
        expect($history->first()->notes)->toBe('Week 3');
        expect($history->last()->notes)->toBe('Week 1');
    });

    it('deletes KPI assignment', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiAssignment($tenant);

        $admin = createTenantUserForKpiAssignment($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create();
        $participant = createParticipantForKpiAssignment($tenant);

        $assignment = KpiAssignment::factory()->create([
            'kpi_template_id' => $template->id,
            'performance_cycle_participant_id' => $participant->id,
        ]);

        $assignmentId = $assignment->id;
        $assignment->delete();

        $this->assertDatabaseMissing('kpi_assignments', [
            'id' => $assignmentId,
        ]);
    });
});
