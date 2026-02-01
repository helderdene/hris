<?php

use App\Enums\ApplicationStatus;
use App\Enums\DtrStatus;
use App\Enums\LeaveApplicationStatus;
use App\Models\DailyTimeRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Services\EmployeeDashboardService;
use App\Services\HRAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForAnalytics(Tenant $tenant): void
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

describe('HRAnalyticsService', function () {
    describe('getHeadcountMetrics', function () {
        it('returns total and active employee counts', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            Employee::factory()->count(5)->active()->create();
            Employee::factory()->count(2)->terminated()->create();

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getHeadcountMetrics();

            expect($metrics['total'])->toBe(7);
            expect($metrics['active'])->toBe(5);
        });

        it('filters by department when department IDs provided', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            $dept1 = Department::factory()->create();
            $dept2 = Department::factory()->create();

            Employee::factory()->count(3)->active()->create(['department_id' => $dept1->id]);
            Employee::factory()->count(2)->active()->create(['department_id' => $dept2->id]);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getHeadcountMetrics([$dept1->id]);

            expect($metrics['total'])->toBe(3);
            expect($metrics['active'])->toBe(3);
        });
    });

    describe('getAttendanceMetrics', function () {
        it('calculates attendance rate correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            // Create 10 employees, each with one DTR record (8 present, 2 absent)
            $presentEmployees = Employee::factory()->count(8)->active()->create();
            $absentEmployees = Employee::factory()->count(2)->active()->create();

            $date = now()->subDays(5);

            foreach ($presentEmployees as $employee) {
                DailyTimeRecord::factory()->forDate($date)->create([
                    'employee_id' => $employee->id,
                    'status' => DtrStatus::Present,
                ]);
            }

            foreach ($absentEmployees as $employee) {
                DailyTimeRecord::factory()->forDate($date)->absent()->create([
                    'employee_id' => $employee->id,
                ]);
            }

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getAttendanceMetrics(
                now()->subDays(10)->toDateString(),
                now()->toDateString()
            );

            expect($metrics['attendanceRate'])->toBe(80.0);
            expect($metrics['presentCount'])->toBe(8);
            expect($metrics['absentCount'])->toBe(2);
            expect($metrics['totalRecords'])->toBe(10);
        });

        it('counts late arrivals correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            // Create 5 employees, each with different late status
            $lateEmployees = Employee::factory()->count(3)->active()->create();
            $onTimeEmployees = Employee::factory()->count(2)->active()->create();

            $date = now()->subDays(3);

            foreach ($lateEmployees as $employee) {
                DailyTimeRecord::factory()->forDate($date)->late(15)->create([
                    'employee_id' => $employee->id,
                ]);
            }

            $date2 = now()->subDays(2);
            foreach ($onTimeEmployees as $employee) {
                DailyTimeRecord::factory()->forDate($date2)->create([
                    'employee_id' => $employee->id,
                    'late_minutes' => 0,
                ]);
            }

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getAttendanceMetrics(
                now()->subDays(10)->toDateString(),
                now()->toDateString()
            );

            expect($metrics['lateCount'])->toBe(3);
        });
    });

    describe('getLeaveMetrics', function () {
        it('calculates leave metrics correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            $employee = Employee::factory()->active()->create();
            $leaveType = LeaveType::factory()->create();

            // Create 3 approved leaves with unique reference numbers
            for ($i = 0; $i < 3; $i++) {
                LeaveApplication::factory()->approved()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => now()->subDays(5 + $i),
                    'end_date' => now()->subDays(4 + $i),
                    'total_days' => 2,
                ]);
            }

            // Create 2 pending leaves
            for ($i = 0; $i < 2; $i++) {
                LeaveApplication::factory()->pending()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => now()->addDays(5 + $i),
                    'end_date' => now()->addDays(6 + $i),
                    'total_days' => 2,
                ]);
            }

            // Create 1 rejected leave
            LeaveApplication::factory()->rejected()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => now()->subDays(10),
                'end_date' => now()->subDays(9),
                'total_days' => 2,
            ]);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getLeaveMetrics(
                now()->subDays(30)->toDateString(),
                now()->addDays(30)->toDateString()
            );

            expect($metrics['totalApplications'])->toBe(6);
            expect($metrics['approvedCount'])->toBe(3);
            expect($metrics['pendingCount'])->toBe(2);
            expect($metrics['rejectedCount'])->toBe(1);
            expect($metrics['totalDaysUsed'])->toBe(6.0);
            expect($metrics['approvalRate'])->toBe(75.0); // 3 approved / 4 decided
        });
    });

    describe('getLeaveTypeBreakdown', function () {
        it('groups leave usage by type', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            $employee = Employee::factory()->active()->create();
            $vacationLeave = LeaveType::factory()->create(['name' => 'Vacation Leave', 'code' => 'VL']);
            $sickLeave = LeaveType::factory()->create(['name' => 'Sick Leave', 'code' => 'SL']);

            // Create 2 vacation leave applications
            for ($i = 0; $i < 2; $i++) {
                LeaveApplication::factory()->approved()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $vacationLeave->id,
                    'start_date' => now()->subDays(5 + $i * 3),
                    'end_date' => now()->subDays(4 + $i * 3),
                    'total_days' => 2,
                ]);
            }

            // Create 1 sick leave
            LeaveApplication::factory()->approved()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $sickLeave->id,
                'start_date' => now()->subDays(3),
                'end_date' => now()->subDays(3),
                'total_days' => 1,
            ]);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $breakdown = $service->getLeaveTypeBreakdown(
                now()->subDays(30)->toDateString(),
                now()->toDateString()
            );

            expect($breakdown)->toHaveCount(2);
            expect(collect($breakdown)->firstWhere('type', 'Vacation Leave')['count'])->toBe(2);
            expect(collect($breakdown)->firstWhere('type', 'Vacation Leave')['days'])->toBe(4.0);
            expect(collect($breakdown)->firstWhere('type', 'Sick Leave')['count'])->toBe(1);
        });
    });

    describe('getSalaryDistribution', function () {
        it('categorizes employees by salary bands', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            // Create employees in different salary bands
            Employee::factory()->active()->create(['basic_salary' => 12000]); // Below 15K
            Employee::factory()->active()->create(['basic_salary' => 20000]); // 15K-25K
            Employee::factory()->active()->create(['basic_salary' => 22000]); // 15K-25K
            Employee::factory()->active()->create(['basic_salary' => 50000]); // 40K-60K
            Employee::factory()->active()->create(['basic_salary' => 120000]); // Above 100K

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $distribution = $service->getSalaryDistribution();

            expect(collect($distribution)->firstWhere('band', 'Below 15K')['count'])->toBe(1);
            expect(collect($distribution)->firstWhere('band', '15K-25K')['count'])->toBe(2);
            expect(collect($distribution)->firstWhere('band', '40K-60K')['count'])->toBe(1);
            expect(collect($distribution)->firstWhere('band', 'Above 100K')['count'])->toBe(1);
        });
    });

    describe('getRecruitmentMetrics', function () {
        it('calculates recruitment metrics correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            $department = Department::factory()->create();
            $jobPosting = JobPosting::factory()->create([
                'department_id' => $department->id,
                'status' => 'published',
            ]);

            JobApplication::factory()->count(5)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Applied,
                'applied_at' => now()->subDays(10),
            ]);
            JobApplication::factory()->count(2)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Hired,
                'applied_at' => now()->subDays(15),
                'hired_at' => now()->subDays(5),
            ]);
            JobApplication::factory()->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Rejected,
                'applied_at' => now()->subDays(12),
            ]);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $metrics = $service->getRecruitmentMetrics(
                now()->subDays(30)->toDateString(),
                now()->toDateString()
            );

            expect($metrics['openPositions'])->toBe(1);
            expect($metrics['totalApplications'])->toBe(8);
            expect($metrics['hiredCount'])->toBe(2);
            expect($metrics['rejectedCount'])->toBe(1);
        });
    });

    describe('getRecruitmentPipeline', function () {
        it('returns pipeline stages with counts', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            $jobPosting = JobPosting::factory()->create();

            JobApplication::factory()->count(3)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Applied,
                'applied_at' => now()->subDays(5),
            ]);
            JobApplication::factory()->count(2)->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Screening,
                'applied_at' => now()->subDays(5),
            ]);
            JobApplication::factory()->create([
                'job_posting_id' => $jobPosting->id,
                'status' => ApplicationStatus::Interview,
                'applied_at' => now()->subDays(5),
            ]);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $pipeline = $service->getRecruitmentPipeline(
                now()->subDays(30)->toDateString(),
                now()->toDateString()
            );

            expect(collect($pipeline)->firstWhere('stage', 'applied')['count'])->toBe(3);
            expect(collect($pipeline)->firstWhere('stage', 'screening')['count'])->toBe(2);
            expect(collect($pipeline)->firstWhere('stage', 'interview')['count'])->toBe(1);
        });
    });

    describe('getDepartments', function () {
        it('returns active departments sorted by name', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForAnalytics($tenant);

            Department::factory()->create(['name' => 'Zebra', 'status' => 'active']);
            Department::factory()->create(['name' => 'Alpha', 'status' => 'active']);
            Department::factory()->create(['name' => 'Beta', 'status' => 'inactive']);

            $service = new HRAnalyticsService(new EmployeeDashboardService);
            $departments = $service->getDepartments();

            expect($departments)->toHaveCount(2);
            expect($departments[0]['name'])->toBe('Alpha');
            expect($departments[1]['name'])->toBe('Zebra');
        });
    });
});
