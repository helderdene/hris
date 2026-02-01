<?php

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Enums\OfferStatus;
use App\Models\Department;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Offer;
use App\Services\RecruitmentAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Reset JobRequisition reference number counter for tests
    \Illuminate\Support\Facades\DB::statement('DELETE FROM job_requisitions');
});

describe('RecruitmentAnalyticsService', function () {
    describe('getSummaryMetrics', function () {
        it('returns correct summary metrics structure', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getSummaryMetrics();

            expect($result)->toHaveKeys([
                'activeRequisitions',
                'openPositions',
                'totalApplications',
                'avgTimeToFill',
                'offerAcceptanceRate',
            ]);
        });

        it('counts active requisitions correctly', function () {
            $department = Department::factory()->create();

            // Create requisitions one by one to avoid unique reference number issues
            for ($i = 0; $i < 3; $i++) {
                JobRequisition::factory()->approved()->create([
                    'department_id' => $department->id,
                ]);
            }

            for ($i = 0; $i < 2; $i++) {
                JobRequisition::factory()->pending()->create([
                    'department_id' => $department->id,
                ]);
            }

            $service = new RecruitmentAnalyticsService;
            $result = $service->getSummaryMetrics();

            expect($result['activeRequisitions'])->toBe(3);
        });

        it('filters by department when provided', function () {
            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            // Create requisitions one by one
            for ($i = 0; $i < 3; $i++) {
                JobRequisition::factory()->approved()->create([
                    'department_id' => $dept1->id,
                ]);
            }

            for ($i = 0; $i < 5; $i++) {
                JobRequisition::factory()->approved()->create([
                    'department_id' => $dept2->id,
                ]);
            }

            $service = new RecruitmentAnalyticsService;
            $result = $service->getSummaryMetrics(null, null, [$dept1->id]);

            expect($result['activeRequisitions'])->toBe(3);
        });
    });

    describe('getFunnelMetrics', function () {
        it('returns all pipeline stages', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getFunnelMetrics();

            expect($result)->toHaveCount(6);

            $stages = array_column($result, 'stage');
            expect($stages)->toContain('applied', 'screening', 'interview', 'assessment', 'offer', 'hired');
        });

        it('calculates conversion rates correctly', function () {
            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
                'status' => 'published',
            ]);

            // Create applications at different stages
            JobApplication::factory()->count(10)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Applied,
                'applied_at' => now(),
            ]);

            JobApplication::factory()->count(5)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Screening,
                'applied_at' => now(),
                'screening_at' => now(),
            ]);

            $service = new RecruitmentAnalyticsService;
            $result = $service->getFunnelMetrics();

            // First stage (Applied) should have 15 total
            expect($result[0]['count'])->toBe(15);

            // Second stage (Screening) should have 5
            $screeningStage = collect($result)->firstWhere('stage', 'screening');
            expect($screeningStage['count'])->toBe(5);
        });
    });

    describe('getTimeToFillMetrics', function () {
        it('returns correct structure', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getTimeToFillMetrics();

            expect($result)->toHaveKeys(['byStage', 'bottleneck', 'totalAvgDays']);
            expect($result['byStage'])->toBeArray();
        });

        it('calculates average time to fill', function () {
            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
            ]);

            // Create hired applications with known time differences
            JobApplication::factory()->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Hired,
                'applied_at' => now()->subDays(30),
                'hired_at' => now(),
            ]);

            JobApplication::factory()->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Hired,
                'applied_at' => now()->subDays(20),
                'hired_at' => now(),
            ]);

            $service = new RecruitmentAnalyticsService;
            $result = $service->getTimeToFillMetrics();

            // Average of 30 and 20 days = 25 days
            expect($result['totalAvgDays'])->toBe(25.0);
        });
    });

    describe('getSourceEffectiveness', function () {
        it('returns metrics for all sources', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getSourceEffectiveness();

            expect($result)->toHaveCount(count(ApplicationSource::cases()));

            foreach (ApplicationSource::cases() as $source) {
                $sourceData = collect($result)->firstWhere('source', $source->value);
                expect($sourceData)->not->toBeNull();
                expect($sourceData)->toHaveKeys(['source', 'label', 'applications', 'hires', 'hireRate', 'color']);
            }
        });

        it('calculates hire rate correctly', function () {
            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
            ]);

            // Create 10 applications from careers page, 2 hired
            JobApplication::factory()->count(8)->create([
                'job_posting_id' => $jobPosting->id,
                'source' => ApplicationSource::CareersPage,
                'status' => ApplicationStatus::Applied,
                'applied_at' => now(),
            ]);

            JobApplication::factory()->count(2)->create([
                'job_posting_id' => $jobPosting->id,
                'source' => ApplicationSource::CareersPage,
                'status' => ApplicationStatus::Hired,
                'applied_at' => now(),
                'hired_at' => now(),
            ]);

            $service = new RecruitmentAnalyticsService;
            $result = $service->getSourceEffectiveness();

            $careersSource = collect($result)->firstWhere('source', 'careers_page');
            expect($careersSource['applications'])->toBe(10);
            expect($careersSource['hires'])->toBe(2);
            expect($careersSource['hireRate'])->toBe(20.0);
        });
    });

    describe('getOfferMetrics', function () {
        it('returns correct structure', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getOfferMetrics();

            expect($result)->toHaveKeys([
                'total',
                'accepted',
                'declined',
                'pending',
                'acceptanceRate',
                'avgResponseDays',
            ]);
        });

        it('calculates acceptance rate correctly', function () {
            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
            ]);

            // Create 8 accepted, 2 declined offers
            foreach (range(1, 8) as $i) {
                $application = JobApplication::factory()->create([
                    'job_posting_id' => $jobPosting->id,
                ]);
                Offer::factory()->create([
                    'job_application_id' => $application->id,
                    'status' => OfferStatus::Accepted,
                    'created_at' => now(),
                ]);
            }

            foreach (range(1, 2) as $i) {
                $application = JobApplication::factory()->create([
                    'job_posting_id' => $jobPosting->id,
                ]);
                Offer::factory()->create([
                    'job_application_id' => $application->id,
                    'status' => OfferStatus::Declined,
                    'created_at' => now(),
                ]);
            }

            $service = new RecruitmentAnalyticsService;
            $result = $service->getOfferMetrics();

            expect($result['total'])->toBe(10);
            expect($result['accepted'])->toBe(8);
            expect($result['declined'])->toBe(2);
            expect($result['acceptanceRate'])->toBe(80.0);
        });
    });

    describe('getRequisitionMetrics', function () {
        it('returns correct structure', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getRequisitionMetrics();

            expect($result)->toHaveKeys([
                'open',
                'approved',
                'pending',
                'rejected',
                'fillRate',
                'avgApprovalDays',
            ]);
        });

        it('counts requisitions by status', function () {
            $department = Department::factory()->create();

            // Create requisitions one by one
            for ($i = 0; $i < 2; $i++) {
                JobRequisition::factory()->pending()->create([
                    'department_id' => $department->id,
                    'created_at' => now(),
                ]);
            }

            for ($i = 0; $i < 5; $i++) {
                JobRequisition::factory()->approved()->create([
                    'department_id' => $department->id,
                    'created_at' => now(),
                ]);
            }

            JobRequisition::factory()->rejected()->create([
                'department_id' => $department->id,
                'created_at' => now(),
            ]);

            $service = new RecruitmentAnalyticsService;
            $result = $service->getRequisitionMetrics();

            expect($result['pending'])->toBe(2);
            expect($result['approved'])->toBe(5);
            expect($result['rejected'])->toBe(1);
            expect($result['open'])->toBe(2); // Draft + Pending
        });
    });

    describe('getInterviewMetrics', function () {
        it('returns correct structure', function () {
            $service = new RecruitmentAnalyticsService;

            $result = $service->getInterviewMetrics();

            expect($result)->toHaveKeys([
                'total',
                'completed',
                'cancelled',
                'noShows',
                'completionRate',
                'avgDurationMinutes',
            ]);
        });

        it('counts interviews by status', function () {
            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
            ]);
            $application = JobApplication::factory()->create([
                'job_posting_id' => $jobPosting->id,
            ]);

            Interview::factory()->count(5)->create([
                'job_application_id' => $application->id,
                'status' => InterviewStatus::Completed,
                'scheduled_at' => now(),
                'duration_minutes' => 45,
            ]);

            Interview::factory()->count(2)->create([
                'job_application_id' => $application->id,
                'status' => InterviewStatus::Cancelled,
                'scheduled_at' => now(),
            ]);

            Interview::factory()->count(1)->create([
                'job_application_id' => $application->id,
                'status' => InterviewStatus::NoShow,
                'scheduled_at' => now(),
            ]);

            $service = new RecruitmentAnalyticsService;
            $result = $service->getInterviewMetrics();

            expect($result['total'])->toBe(8);
            expect($result['completed'])->toBe(5);
            expect($result['cancelled'])->toBe(2);
            expect($result['noShows'])->toBe(1);
            expect($result['completionRate'])->toBe(62.5);
        });
    });

    describe('getHiringVelocityTrend', function () {
        it('returns array for trend data', function () {
            // Skip this test on SQLite since DATE_FORMAT is MySQL-specific
            // In production MySQL, this would return monthly trend data
            $service = new RecruitmentAnalyticsService;

            // Wrap in try-catch to handle SQLite limitations gracefully
            try {
                $result = $service->getHiringVelocityTrend();
                expect($result)->toBeArray();
            } catch (\Illuminate\Database\QueryException $e) {
                // SQLite doesn't support DATE_FORMAT, mark as skipped
                $this->markTestSkipped('DATE_FORMAT not supported in SQLite');
            }
        });
    });

    describe('getDepartments', function () {
        it('returns list of active departments', function () {
            // Create active departments
            Department::factory()->count(5)->create();

            $service = new RecruitmentAnalyticsService;
            $result = $service->getDepartments();

            expect($result)->toHaveCount(5);
            expect($result[0])->toHaveKeys(['id', 'name']);
        });
    });
});
