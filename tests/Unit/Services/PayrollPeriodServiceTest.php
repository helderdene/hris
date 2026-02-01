<?php

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Services\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPayrollService(Tenant $tenant): void
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

describe('PayrollPeriodService', function () {
    describe('generatePeriodsForYear', function () {
        it('generates 24 periods for semi-monthly cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generatePeriodsForYear($cycle, 2026);

            expect($periods)->toHaveCount(24);

            // Verify period numbers are sequential 1-24
            $periodNumbers = $periods->pluck('period_number')->toArray();
            expect($periodNumbers)->toBe(range(1, 24));

            // Verify all are draft status
            expect($periods->where('status', PayrollPeriodStatus::Draft)->count())->toBe(24);

            // Verify year is set correctly
            expect($periods->where('year', 2026)->count())->toBe(24);
        });

        it('generates 12 periods for monthly cycle', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->monthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generatePeriodsForYear($cycle, 2026);

            expect($periods)->toHaveCount(12);

            // Verify period numbers are sequential 1-12
            $periodNumbers = $periods->pluck('period_number')->toArray();
            expect($periodNumbers)->toBe(range(1, 12));

            // Verify all are draft status
            expect($periods->where('status', PayrollPeriodStatus::Draft)->count())->toBe(12);
        });

        it('throws exception for non-recurring cycle types', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->supplemental()->create();
            $service = new PayrollPeriodService;

            $service->generatePeriodsForYear($cycle, 2026);
        })->throws(\InvalidArgumentException::class);

        it('does not create duplicate periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            // Generate first time
            $firstGeneration = $service->generatePeriodsForYear($cycle, 2026);
            expect($firstGeneration)->toHaveCount(24);

            // Generate second time without overwrite
            $secondGeneration = $service->generatePeriodsForYear($cycle, 2026, false);
            expect($secondGeneration)->toHaveCount(0);

            // Database should still have 24 periods
            $totalPeriods = PayrollPeriod::forCycle($cycle->id)->forYear(2026)->count();
            expect($totalPeriods)->toBe(24);
        });

        it('overwrites draft periods when overwrite flag is true', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            // Generate first time
            $service->generatePeriodsForYear($cycle, 2026);

            // Generate again with overwrite
            $secondGeneration = $service->generatePeriodsForYear($cycle, 2026, true);
            expect($secondGeneration)->toHaveCount(24);

            // Database should still have 24 periods (old deleted, new created)
            $totalPeriods = PayrollPeriod::forCycle($cycle->id)->forYear(2026)->count();
            expect($totalPeriods)->toBe(24);
        });

        it('does not overwrite non-draft periods when overwrite flag is true', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            // Generate periods
            $service->generatePeriodsForYear($cycle, 2026);

            // Change first period to Open status
            $firstPeriod = PayrollPeriod::forCycle($cycle->id)->forYear(2026)->where('period_number', 1)->first();
            $firstPeriod->update(['status' => PayrollPeriodStatus::Open]);

            // Generate again with overwrite - should not create period 1
            $secondGeneration = $service->generatePeriodsForYear($cycle, 2026, true);
            expect($secondGeneration)->toHaveCount(23); // 24 - 1 (the open period)

            // The open period should still exist
            $openPeriod = PayrollPeriod::forCycle($cycle->id)->forYear(2026)->where('period_number', 1)->first();
            expect($openPeriod->status)->toBe(PayrollPeriodStatus::Open);
        });
    });

    describe('generateSemiMonthlyPeriods', function () {
        it('sets correct cutoff dates for first half periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generateSemiMonthlyPeriods($cycle, 2026);

            // Check January first half (period 1)
            $januaryFirstHalf = $periods->firstWhere('period_number', 1);
            expect($januaryFirstHalf->cutoff_start->format('Y-m-d'))->toBe('2026-01-01');
            expect($januaryFirstHalf->cutoff_end->format('Y-m-d'))->toBe('2026-01-15');
            expect($januaryFirstHalf->name)->toBe('January 2026 - 1st Half');
        });

        it('sets correct cutoff dates for second half periods', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generateSemiMonthlyPeriods($cycle, 2026);

            // Check January second half (period 2)
            $januarySecondHalf = $periods->firstWhere('period_number', 2);
            expect($januarySecondHalf->cutoff_start->format('Y-m-d'))->toBe('2026-01-16');
            expect($januarySecondHalf->cutoff_end->format('Y-m-d'))->toBe('2026-01-31');
            expect($januarySecondHalf->name)->toBe('January 2026 - 2nd Half');
        });

        it('handles February end-of-month correctly', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            // 2026 is not a leap year
            $periods = $service->generateSemiMonthlyPeriods($cycle, 2026);
            $februarySecondHalf = $periods->firstWhere('period_number', 4);
            expect($februarySecondHalf->cutoff_end->format('Y-m-d'))->toBe('2026-02-28');

            // Test leap year (2024)
            $cycle2 = PayrollCycle::factory()->semiMonthly()->create();
            $periods2024 = $service->generateSemiMonthlyPeriods($cycle2, 2024);
            $februarySecondHalf2024 = $periods2024->firstWhere('period_number', 4);
            expect($februarySecondHalf2024->cutoff_end->format('Y-m-d'))->toBe('2024-02-29');
        });
    });

    describe('generateMonthlyPeriods', function () {
        it('generates monthly periods with correct names', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->monthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generateMonthlyPeriods($cycle, 2026);

            expect($periods->firstWhere('period_number', 1)->name)->toBe('January 2026');
            expect($periods->firstWhere('period_number', 6)->name)->toBe('June 2026');
            expect($periods->firstWhere('period_number', 12)->name)->toBe('December 2026');
        });

        it('sets correct cutoff dates for each month', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->monthly()->create();
            $service = new PayrollPeriodService;

            $periods = $service->generateMonthlyPeriods($cycle, 2026);

            // Check January
            $january = $periods->firstWhere('period_number', 1);
            expect($january->cutoff_start->format('Y-m-d'))->toBe('2026-01-01');
            expect($january->cutoff_end->format('Y-m-d'))->toBe('2026-01-31');

            // Check February (28 days in 2026)
            $february = $periods->firstWhere('period_number', 2);
            expect($february->cutoff_start->format('Y-m-d'))->toBe('2026-02-01');
            expect($february->cutoff_end->format('Y-m-d'))->toBe('2026-02-28');
        });
    });

    describe('getYearSummary', function () {
        it('returns correct summary statistics', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();

            // Create periods with different statuses
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(1)->draft()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(2)->open()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(3)->processing()->create();
            PayrollPeriod::factory()->forCycle($cycle)->forYear(2026)->periodNumber(4)->closed()
                ->create([
                    'employee_count' => 50,
                    'total_gross' => 500000.00,
                    'total_net' => 400000.00,
                ]);

            $service = new PayrollPeriodService;
            $summary = $service->getYearSummary($cycle, 2026);

            expect($summary['total_periods'])->toBe(4);
            expect($summary['by_status']['draft'])->toBe(1);
            expect($summary['by_status']['open'])->toBe(1);
            expect($summary['by_status']['processing'])->toBe(1);
            expect($summary['by_status']['closed'])->toBe(1);
            expect($summary['total_gross'])->toBe(500000.00);
            expect($summary['total_net'])->toBe(400000.00);
            expect($summary['total_employees_paid'])->toBe(50);
        });
    });

    describe('findPeriodForDate', function () {
        it('finds the correct period for a given date', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            $service->generatePeriodsForYear($cycle, 2026);

            $period = $service->findPeriodForDate($cycle, \Carbon\Carbon::parse('2026-01-10'));
            expect($period)->not->toBeNull();
            expect($period->period_number)->toBe(1); // First half of January

            $period2 = $service->findPeriodForDate($cycle, \Carbon\Carbon::parse('2026-01-20'));
            expect($period2)->not->toBeNull();
            expect($period2->period_number)->toBe(2); // Second half of January
        });

        it('returns null when no period contains the date', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayrollService($tenant);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $service = new PayrollPeriodService;

            // Don't generate any periods
            $period = $service->findPeriodForDate($cycle, \Carbon\Carbon::parse('2026-01-10'));
            expect($period)->toBeNull();
        });
    });
});
