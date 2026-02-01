<?php

use App\Enums\DevelopmentPlanStatus;
use App\Enums\EvaluationStatus;
use App\Enums\GoalStatus;
use App\Enums\KpiAssignmentStatus;
use App\Models\Department;
use App\Models\DevelopmentPlan;
use App\Models\Employee;
use App\Models\EvaluationSummary;
use App\Models\Goal;
use App\Models\KpiAssignment;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Services\PerformanceAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPerformanceService(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('PerformanceAnalyticsService', function () {
    describe('getSummaryMetrics', function () {
        it('returns correct structure for summary metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;

            $result = $service->getSummaryMetrics();

            expect($result)->toHaveKey('totalEvaluations')
                ->and($result)->toHaveKey('completedEvaluations')
                ->and($result)->toHaveKey('averageRating')
                ->and($result)->toHaveKey('activeDevelopmentPlans')
                ->and($result)->toHaveKey('activeGoals')
                ->and($result)->toHaveKey('goalsAchieved');
        });

        it('counts active development plans', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            DevelopmentPlan::factory()->count(3)->create([
                'status' => DevelopmentPlanStatus::InProgress,
            ]);
            DevelopmentPlan::factory()->count(2)->create([
                'status' => DevelopmentPlanStatus::Completed,
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getSummaryMetrics();

            expect($result['activeDevelopmentPlans'])->toBe(3);
        });

        it('counts active goals', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            Goal::factory()->count(4)->create([
                'status' => GoalStatus::Active,
            ]);
            Goal::factory()->count(2)->create([
                'status' => GoalStatus::Completed,
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getSummaryMetrics();

            expect($result['activeGoals'])->toBe(4);
        });

        it('filters by department IDs', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            $emp1 = Employee::factory()->create(['department_id' => $dept1->id]);
            $emp2 = Employee::factory()->create(['department_id' => $dept2->id]);

            Goal::factory()->count(3)->create([
                'employee_id' => $emp1->id,
                'status' => GoalStatus::Active,
            ]);
            Goal::factory()->count(2)->create([
                'employee_id' => $emp2->id,
                'status' => GoalStatus::Active,
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getSummaryMetrics([$dept1->id]);

            expect($result['activeGoals'])->toBe(3);
        });
    });

    describe('getRatingDistribution', function () {
        it('returns correct structure for rating distribution', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getRatingDistribution();

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(5);

            // Check first item structure
            expect($result[0])->toHaveKey('rating')
                ->and($result[0])->toHaveKey('count')
                ->and($result[0])->toHaveKey('label')
                ->and($result[0])->toHaveKey('percentage');
        });

        it('returns ratings in correct order', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getRatingDistribution();

            $expectedOrder = [
                'exceptional',
                'exceeds_expectations',
                'meets_expectations',
                'needs_improvement',
                'unsatisfactory',
            ];

            foreach ($expectedOrder as $index => $rating) {
                expect($result[$index]['rating'])->toBe($rating);
            }
        });
    });

    describe('getDevelopmentPlanMetrics', function () {
        it('returns correct structure for development plan metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getDevelopmentPlanMetrics();

            expect($result)->toHaveKey('byStatus')
                ->and($result)->toHaveKey('completionRate')
                ->and($result)->toHaveKey('overdueCount')
                ->and($result)->toHaveKey('averageProgress')
                ->and($result)->toHaveKey('totalPlans');
        });

        it('calculates completion rate correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            DevelopmentPlan::factory()->count(3)->create([
                'status' => DevelopmentPlanStatus::Completed,
            ]);
            DevelopmentPlan::factory()->count(7)->create([
                'status' => DevelopmentPlanStatus::InProgress,
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getDevelopmentPlanMetrics();

            expect($result['completionRate'])->toBe(30.0);
            expect($result['totalPlans'])->toBe(10);
        });

        it('counts overdue plans correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            // Active but past due date
            DevelopmentPlan::factory()->count(2)->create([
                'status' => DevelopmentPlanStatus::InProgress,
                'target_completion_date' => Carbon::now()->subDays(10),
            ]);

            // Active and not past due
            DevelopmentPlan::factory()->count(3)->create([
                'status' => DevelopmentPlanStatus::InProgress,
                'target_completion_date' => Carbon::now()->addDays(30),
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getDevelopmentPlanMetrics();

            expect($result['overdueCount'])->toBe(2);
        });
    });

    describe('getGoalAchievementMetrics', function () {
        it('returns correct structure for goal achievement metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getGoalAchievementMetrics();

            expect($result)->toHaveKey('byStatus')
                ->and($result)->toHaveKey('byPriority')
                ->and($result)->toHaveKey('achievementRate')
                ->and($result)->toHaveKey('averageProgress')
                ->and($result)->toHaveKey('totalGoals')
                ->and($result)->toHaveKey('overdueCount');
        });

        it('calculates achievement rate correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            Goal::factory()->count(2)->create([
                'status' => GoalStatus::Completed,
            ]);
            Goal::factory()->count(8)->create([
                'status' => GoalStatus::Active,
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getGoalAchievementMetrics();

            expect($result['achievementRate'])->toBe(20.0);
            expect($result['totalGoals'])->toBe(10);
        });

        it('counts overdue goals correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            // Active but past due date
            Goal::factory()->count(3)->create([
                'status' => GoalStatus::Active,
                'due_date' => Carbon::now()->subDays(5),
            ]);

            // Active and not past due
            Goal::factory()->count(2)->create([
                'status' => GoalStatus::Active,
                'due_date' => Carbon::now()->addDays(30),
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getGoalAchievementMetrics();

            expect($result['overdueCount'])->toBe(3);
        });
    });

    describe('getKpiAchievementMetrics', function () {
        it('returns correct structure for KPI achievement metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getKpiAchievementMetrics();

            expect($result)->toHaveKey('byStatus')
                ->and($result)->toHaveKey('achievementDistribution')
                ->and($result)->toHaveKey('averageAchievement')
                ->and($result)->toHaveKey('totalKpis')
                ->and($result)->toHaveKey('overachievingCount');
        });

        it('returns correct achievement distribution ranges', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getKpiAchievementMetrics();

            $expectedRanges = ['0-50%', '50-75%', '75-100%', '100-150%', '150%+'];

            foreach ($expectedRanges as $index => $range) {
                expect($result['achievementDistribution'][$index]['range'])->toBe($range);
            }
        });
    });

    describe('getMetricsByDepartment', function () {
        it('returns correct structure for department metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            Department::factory()->count(2)->create();

            $service = new PerformanceAnalyticsService;
            $result = $service->getMetricsByDepartment();

            expect($result)->toBeArray();

            if (count($result) > 0) {
                expect($result[0])->toHaveKey('department')
                    ->and($result[0])->toHaveKey('departmentId')
                    ->and($result[0])->toHaveKey('evaluations')
                    ->and($result[0])->toHaveKey('completedEvaluations')
                    ->and($result[0])->toHaveKey('averageRating')
                    ->and($result[0])->toHaveKey('developmentPlans')
                    ->and($result[0])->toHaveKey('goals')
                    ->and($result[0])->toHaveKey('goalsAchieved');
            }
        });

        it('filters by department IDs when provided', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();
            $dept3 = Department::factory()->create();

            $service = new PerformanceAnalyticsService;
            $result = $service->getMetricsByDepartment([$dept1->id, $dept2->id]);

            expect(count($result))->toBe(2);
        });
    });

    describe('getEvaluationCompletionMetrics', function () {
        it('returns correct structure for evaluation completion metrics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getEvaluationCompletionMetrics();

            expect($result)->toHaveKey('byStatus')
                ->and($result)->toHaveKey('byCycle')
                ->and($result)->toHaveKey('overallRate');
        });

        it('returns status breakdown', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getEvaluationCompletionMetrics();

            // byStatus should contain evaluation status items
            expect($result['byStatus'])->toBeArray();

            if (count($result['byStatus']) > 0) {
                expect($result['byStatus'][0])->toHaveKey('status')
                    ->and($result['byStatus'][0])->toHaveKey('label')
                    ->and($result['byStatus'][0])->toHaveKey('count');
            }
        });
    });

    describe('getRatingTrends', function () {
        it('returns correct structure for rating trends', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getRatingTrends();

            expect($result)->toHaveKey('cycles')
                ->and($result)->toHaveKey('series');

            expect($result['cycles'])->toBeArray();
            expect($result['series'])->toBeArray();
        });

        it('returns all rating categories in series', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $service = new PerformanceAnalyticsService;
            $result = $service->getRatingTrends();

            expect(count($result['series']))->toBe(5);

            $expectedRatings = [
                'exceptional',
                'exceeds_expectations',
                'meets_expectations',
                'needs_improvement',
                'unsatisfactory',
            ];

            foreach ($expectedRatings as $index => $rating) {
                expect($result['series'][$index]['rating'])->toBe($rating);
            }
        });
    });

    describe('date range filtering', function () {
        it('respects date range for goals achieved count', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPerformanceService($tenant);

            $rangeStart = Carbon::now()->subMonth();
            $rangeEnd = Carbon::now();

            // Goals achieved in range
            Goal::factory()->count(3)->create([
                'status' => GoalStatus::Completed,
                'completed_at' => Carbon::now()->subDays(15),
            ]);

            // Goals achieved before range
            Goal::factory()->count(2)->create([
                'status' => GoalStatus::Completed,
                'completed_at' => Carbon::now()->subMonths(3),
            ]);

            $service = new PerformanceAnalyticsService;
            $result = $service->getSummaryMetrics(null, $rangeStart, $rangeEnd);

            expect($result['goalsAchieved'])->toBe(3);
        });
    });
});
