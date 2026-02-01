<?php

/**
 * API Tests for Competency Framework
 *
 * Tests the CRUD endpoints for managing competencies, position competencies,
 * proficiency levels, and competency evaluations.
 *
 * Note: These tests call controllers directly following the pattern from
 * existing tests since tenant subdomain routing requires special handling.
 */

use App\Enums\CompetencyCategory;
use App\Enums\JobLevel;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CompetencyController;
use App\Http\Controllers\Api\CompetencyEvaluationController;
use App\Http\Controllers\Api\PositionCompetencyController;
use App\Http\Controllers\Api\ProficiencyLevelController;
use App\Http\Requests\StoreCompetencyEvaluationRequest;
use App\Http\Requests\StoreCompetencyRequest;
use App\Http\Requests\StorePositionCompetencyRequest;
use App\Http\Requests\UpdateCompetencyRequest;
use App\Models\Competency;
use App\Models\CompetencyEvaluation;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Position;
use App\Models\PositionCompetency;
use App\Models\ProficiencyLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindCompetencyTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCompetencyUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store competency request.
 */
function createStoreCompetencyRequest(array $data, User $user): StoreCompetencyRequest
{
    $request = StoreCompetencyRequest::create('/api/performance/competencies', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreCompetencyRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update competency request.
 */
function createUpdateCompetencyRequest(array $data, User $user): UpdateCompetencyRequest
{
    $request = UpdateCompetencyRequest::create('/api/performance/competencies/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UpdateCompetencyRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant migrations
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Create a test tenant and bind it
    $this->tenant = Tenant::factory()->create();
    bindCompetencyTenant($this->tenant);

    // Seed proficiency levels
    Artisan::call('db:seed', ['--class' => 'ProficiencyLevelSeeder', '--no-interaction' => true]);
});

describe('Competency CRUD', function () {
    it('lists all competencies', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        Competency::factory()->count(3)->create();

        $controller = new CompetencyController;
        $request = new Request;
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('creates a competency', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $data = [
            'name' => 'Communication Skills',
            'code' => 'COMM-001',
            'description' => 'Ability to communicate effectively.',
            'category' => CompetencyCategory::Core->value,
            'is_active' => true,
        ];

        $request = createStoreCompetencyRequest($data, $admin);

        $controller = new CompetencyController;
        $response = $controller->store($request);

        $this->assertDatabaseHas('competencies', [
            'name' => 'Communication Skills',
            'code' => 'COMM-001',
        ]);
    });

    it('updates a competency', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $competency = Competency::factory()->create([
            'name' => 'Old Name',
            'code' => 'OLD-001',
        ]);

        $data = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ];

        $request = createUpdateCompetencyRequest($data, $admin);

        $controller = new CompetencyController;
        $response = $controller->update($request, $this->tenant->slug, $competency);

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'name' => 'Updated Name',
        ]);
    });

    it('soft deletes a competency', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $competency = Competency::factory()->create();

        $controller = new CompetencyController;
        $response = $controller->destroy($this->tenant->slug, $competency);

        $this->assertSoftDeleted('competencies', ['id' => $competency->id]);
    });
});

describe('Proficiency Level', function () {
    it('lists all proficiency levels', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $controller = new ProficiencyLevelController;
        $response = $controller->index();

        expect($response->count())->toBe(5);

        $levels = collect($response->toArray(request()))->pluck('level')->sort()->values();
        expect($levels->toArray())->toBe([1, 2, 3, 4, 5]);
    });
});

describe('Position Competency Matrix', function () {
    it('assigns competency to position', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $position = Position::factory()->create();
        $competency = Competency::factory()->create();

        $data = [
            'position_id' => $position->id,
            'competency_id' => $competency->id,
            'job_level' => JobLevel::Mid->value,
            'required_proficiency_level' => 3,
            'is_mandatory' => true,
            'weight' => 5,
        ];

        $request = StorePositionCompetencyRequest::create('/api/performance/position-competencies', 'POST', $data);
        $request->setUserResolver(fn () => $admin);
        $request->setContainer(app());

        $validator = Validator::make($data, (new StorePositionCompetencyRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $controller = new PositionCompetencyController;
        $response = $controller->store($request);

        $this->assertDatabaseHas('position_competencies', [
            'position_id' => $position->id,
            'competency_id' => $competency->id,
            'job_level' => JobLevel::Mid->value,
            'required_proficiency_level' => 3,
        ]);
    });

    it('lists position competencies filtered by position', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $position = Position::factory()->create();
        PositionCompetency::factory()->count(2)->create(['position_id' => $position->id]);
        PositionCompetency::factory()->count(3)->create(); // Different positions

        $request = new Request(['position_id' => $position->id]);

        $controller = new PositionCompetencyController;
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });
});

describe('Competency Evaluation', function () {
    it('creates competency evaluation for participant', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $position = Position::factory()->create();
        $employee = Employee::factory()->create(['position_id' => $position->id]);
        $cycle = PerformanceCycle::factory()->create();
        $instance = PerformanceCycleInstance::factory()->create([
            'performance_cycle_id' => $cycle->id,
        ]);
        $participant = PerformanceCycleParticipant::factory()->create([
            'performance_cycle_instance_id' => $instance->id,
            'employee_id' => $employee->id,
        ]);

        $competency = Competency::factory()->create();
        $positionCompetency = PositionCompetency::factory()->create([
            'position_id' => $position->id,
            'competency_id' => $competency->id,
            'job_level' => JobLevel::Mid->value,
        ]);

        $data = [
            'performance_cycle_participant_id' => $participant->id,
            'position_competency_id' => $positionCompetency->id,
            'self_rating' => 3,
            'self_comments' => 'I demonstrate this competency well.',
        ];

        $request = StoreCompetencyEvaluationRequest::create('/api/performance/competency-evaluations', 'POST', $data);
        $request->setUserResolver(fn () => $admin);
        $request->setContainer(app());

        $validator = Validator::make($data, (new StoreCompetencyEvaluationRequest)->rules());
        $validator->validate();

        $reflection = new ReflectionClass($request);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $property->setValue($request, $validator);

        $controller = new CompetencyEvaluationController;
        $response = $controller->store($request);

        $this->assertDatabaseHas('competency_evaluations', [
            'performance_cycle_participant_id' => $participant->id,
            'position_competency_id' => $positionCompetency->id,
            'self_rating' => 3,
        ]);
    });

    it('submits self rating for existing evaluation', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $evaluation = CompetencyEvaluation::factory()->create();

        $request = new Request([
            'self_rating' => 4,
            'self_comments' => 'Updated self assessment.',
        ]);
        $request->setUserResolver(fn () => $admin);

        $controller = new CompetencyEvaluationController;
        $response = $controller->submitSelfRating($request, $this->tenant->slug, $evaluation);

        $evaluation->refresh();
        expect($evaluation->self_rating)->toBe(4);
        expect($evaluation->self_comments)->toBe('Updated self assessment.');
    });

    it('submits manager rating with final rating', function () {
        $admin = createCompetencyUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-organization', fn () => true);

        $evaluation = CompetencyEvaluation::factory()->create([
            'self_rating' => 3,
        ]);

        $request = new Request([
            'manager_rating' => 4,
            'manager_comments' => 'Good performance.',
            'final_rating' => 4,
        ]);
        $request->setUserResolver(fn () => $admin);

        $controller = new CompetencyEvaluationController;
        $response = $controller->submitManagerRating($request, $this->tenant->slug, $evaluation);

        $evaluation->refresh();
        expect($evaluation->manager_rating)->toBe(4);
        expect($evaluation->final_rating)->toBe(4);
        expect($evaluation->evaluated_at)->not()->toBeNull();
        expect($evaluation->isComplete())->toBeTrue();
    });
});

describe('Position Model Competencies Relationship', function () {
    it('has positionCompetencies relationship', function () {
        $position = Position::factory()->create();
        $competencies = Competency::factory()->count(3)->create();

        foreach ($competencies as $competency) {
            PositionCompetency::factory()->create([
                'position_id' => $position->id,
                'competency_id' => $competency->id,
            ]);
        }

        expect($position->positionCompetencies)->toHaveCount(3);
    });

    it('can filter competencies by job level', function () {
        $position = Position::factory()->create();
        $competencies = Competency::factory()->count(4)->create();

        // Create 2 for Junior, 2 for Senior
        PositionCompetency::factory()->create([
            'position_id' => $position->id,
            'competency_id' => $competencies[0]->id,
            'job_level' => JobLevel::Junior->value,
        ]);
        PositionCompetency::factory()->create([
            'position_id' => $position->id,
            'competency_id' => $competencies[1]->id,
            'job_level' => JobLevel::Junior->value,
        ]);
        PositionCompetency::factory()->create([
            'position_id' => $position->id,
            'competency_id' => $competencies[2]->id,
            'job_level' => JobLevel::Senior->value,
        ]);
        PositionCompetency::factory()->create([
            'position_id' => $position->id,
            'competency_id' => $competencies[3]->id,
            'job_level' => JobLevel::Senior->value,
        ]);

        expect($position->competenciesForLevel(JobLevel::Junior)->count())->toBe(2);
        expect($position->competenciesForLevel(JobLevel::Senior)->count())->toBe(2);
    });
});

describe('Competency Model', function () {
    it('uses soft deletes', function () {
        $competency = Competency::factory()->create();
        $competency->delete();

        expect(Competency::withTrashed()->find($competency->id))->not()->toBeNull();
        expect(Competency::find($competency->id))->toBeNull();
    });

    it('has active scope', function () {
        Competency::factory()->count(2)->create(['is_active' => true]);
        Competency::factory()->count(3)->create(['is_active' => false]);

        expect(Competency::active()->count())->toBe(2);
    });

    it('filters by category', function () {
        Competency::factory()->create(['category' => CompetencyCategory::Core->value]);
        Competency::factory()->create(['category' => CompetencyCategory::Core->value]);
        Competency::factory()->create(['category' => CompetencyCategory::Technical->value]);

        expect(Competency::byCategory(CompetencyCategory::Core)->count())->toBe(2);
        expect(Competency::byCategory(CompetencyCategory::Technical)->count())->toBe(1);
    });
});

describe('ProficiencyLevel Model', function () {
    it('finds by level', function () {
        $level3 = ProficiencyLevel::findByLevel(3);

        expect($level3)->not()->toBeNull();
        expect($level3->level)->toBe(3);
        expect($level3->name)->toBe('Competent');
    });

    it('orders by level ascending', function () {
        $levels = ProficiencyLevel::ordered()->pluck('level')->toArray();

        expect($levels)->toBe([1, 2, 3, 4, 5]);
    });
});

describe('CompetencyEvaluation Model', function () {
    it('calculates rating gap correctly', function () {
        $evaluation = CompetencyEvaluation::factory()->create([
            'self_rating' => 4,
            'manager_rating' => 3,
        ]);

        expect($evaluation->getRatingGap())->toBe(1);
    });

    it('returns null rating gap when ratings are missing', function () {
        $evaluation = CompetencyEvaluation::factory()->create([
            'self_rating' => 4,
            'manager_rating' => null,
        ]);

        expect($evaluation->getRatingGap())->toBeNull();
    });

    it('calculates proficiency gap correctly', function () {
        $positionCompetency = PositionCompetency::factory()->create([
            'required_proficiency_level' => 3,
        ]);

        $evaluation = CompetencyEvaluation::factory()->create([
            'position_competency_id' => $positionCompetency->id,
            'final_rating' => 4,
        ]);

        expect($evaluation->getProficiencyGap())->toBe(1);
    });

    it('identifies complete evaluation', function () {
        $evaluation = CompetencyEvaluation::factory()->create([
            'self_rating' => 3,
            'manager_rating' => 4,
            'final_rating' => 4,
            'evaluated_at' => now(),
        ]);

        expect($evaluation->isComplete())->toBeTrue();
    });

    it('identifies incomplete evaluation', function () {
        $evaluation = CompetencyEvaluation::factory()->create([
            'self_rating' => 3,
            'manager_rating' => null,
            'final_rating' => null,
        ]);

        expect($evaluation->isComplete())->toBeFalse();
    });
});
