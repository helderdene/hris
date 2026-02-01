<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Employee;
use Carbon\Carbon;

class EmployeeDashboardService
{
    /**
     * Get total headcount metrics.
     *
     * @return array{total: int, active: int}
     */
    public function getTotalHeadcount(): array
    {
        $total = Employee::query()->count();
        $active = Employee::query()->active()->count();

        return [
            'total' => $total,
            'active' => $active,
        ];
    }

    /**
     * Get new hires metrics for the current month with percentage change vs previous month.
     *
     * @return array{count: int, percentageChange: float|null}
     */
    public function getNewHiresMetrics(): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $currentMonthCount = Employee::query()
            ->whereBetween('hire_date', [$currentMonthStart, $currentMonthEnd])
            ->count();

        $previousMonthCount = Employee::query()
            ->whereBetween('hire_date', [$previousMonthStart, $previousMonthEnd])
            ->count();

        $percentageChange = $this->calculatePercentageChange($previousMonthCount, $currentMonthCount);

        return [
            'count' => $currentMonthCount,
            'percentageChange' => $percentageChange,
        ];
    }

    /**
     * Get separations metrics for the current month with percentage change vs previous month.
     *
     * Separations include employees with termination_date in current month
     * and non-active status (Resigned, Terminated, Retired, EndOfContract, Deceased).
     *
     * @return array{count: int, percentageChange: float|null}
     */
    public function getSeparationsMetrics(): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $nonActiveStatuses = [
            EmploymentStatus::Resigned,
            EmploymentStatus::Terminated,
            EmploymentStatus::Retired,
            EmploymentStatus::EndOfContract,
            EmploymentStatus::Deceased,
        ];

        $currentMonthCount = Employee::query()
            ->whereBetween('termination_date', [$currentMonthStart, $currentMonthEnd])
            ->whereIn('employment_status', $nonActiveStatuses)
            ->count();

        $previousMonthCount = Employee::query()
            ->whereBetween('termination_date', [$previousMonthStart, $previousMonthEnd])
            ->whereIn('employment_status', $nonActiveStatuses)
            ->count();

        $percentageChange = $this->calculatePercentageChange($previousMonthCount, $currentMonthCount);

        return [
            'count' => $currentMonthCount,
            'percentageChange' => $percentageChange,
        ];
    }

    /**
     * Get turnover rate metrics.
     *
     * Formula: (Monthly Separations / Average Headcount) x 12 x 100
     * Average headcount = (Start of month headcount + End of month headcount) / 2
     *
     * @return array{rate: float, averageTenure: float}
     */
    public function getTurnoverRate(): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        // Get separations for current month
        $nonActiveStatuses = [
            EmploymentStatus::Resigned,
            EmploymentStatus::Terminated,
            EmploymentStatus::Retired,
            EmploymentStatus::EndOfContract,
            EmploymentStatus::Deceased,
        ];

        $monthlySeparations = Employee::query()
            ->whereBetween('termination_date', [$currentMonthStart, $currentMonthEnd])
            ->whereIn('employment_status', $nonActiveStatuses)
            ->count();

        // Calculate headcount at start and end of month
        // Start of month: employees hired before month start who were not terminated before month start
        $startOfMonthHeadcount = Employee::query()
            ->where('hire_date', '<', $currentMonthStart)
            ->where(function ($query) use ($currentMonthStart) {
                $query->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $currentMonthStart);
            })
            ->count();

        // End of month: employees hired on or before month end who are not terminated before month end
        $endOfMonthHeadcount = Employee::query()
            ->where('hire_date', '<=', $currentMonthEnd)
            ->where(function ($query) use ($currentMonthEnd) {
                $query->whereNull('termination_date')
                    ->orWhere('termination_date', '>', $currentMonthEnd);
            })
            ->count();

        $averageHeadcount = ($startOfMonthHeadcount + $endOfMonthHeadcount) / 2;

        // Calculate annualized turnover rate
        $rate = $averageHeadcount > 0
            ? round(($monthlySeparations / $averageHeadcount) * 12 * 100, 1)
            : 0;

        // Calculate average tenure for active employees
        $averageTenure = $this->calculateAverageTenure();

        return [
            'rate' => $rate,
            'averageTenure' => $averageTenure,
        ];
    }

    /**
     * Get tenure distribution for active employees.
     *
     * Returns counts for 5 buckets: <1 year, 1-3 years, 3-5 years, 5-10 years, >10 years
     *
     * @return array{lessThan1Year: int, oneToThreeYears: int, threeToFiveYears: int, fiveToTenYears: int, moreThan10Years: int}
     */
    public function getTenureDistribution(): array
    {
        $now = Carbon::now();

        $activeEmployees = Employee::query()
            ->active()
            ->whereNotNull('hire_date')
            ->get();

        $distribution = [
            'lessThan1Year' => 0,
            'oneToThreeYears' => 0,
            'threeToFiveYears' => 0,
            'fiveToTenYears' => 0,
            'moreThan10Years' => 0,
        ];

        foreach ($activeEmployees as $employee) {
            $yearsOfService = $employee->hire_date->diffInYears($now);

            if ($yearsOfService < 1) {
                $distribution['lessThan1Year']++;
            } elseif ($yearsOfService < 3) {
                $distribution['oneToThreeYears']++;
            } elseif ($yearsOfService < 5) {
                $distribution['threeToFiveYears']++;
            } elseif ($yearsOfService < 10) {
                $distribution['fiveToTenYears']++;
            } else {
                $distribution['moreThan10Years']++;
            }
        }

        return $distribution;
    }

    /**
     * Get employment type breakdown for active employees.
     *
     * @return array<string, int>
     */
    public function getEmploymentTypeBreakdown(): array
    {
        $breakdown = [];

        foreach (EmploymentType::cases() as $type) {
            $breakdown[$type->value] = Employee::query()
                ->active()
                ->where('employment_type', $type)
                ->count();
        }

        return $breakdown;
    }

    /**
     * Get department headcounts with active employees only.
     *
     * @return array<int, array{id: int, name: string, employees_count: int}>
     */
    public function getDepartmentHeadcounts(): array
    {
        $departments = Department::query()
            ->active()
            ->withCount(['employees' => function ($query) {
                $query->active();
            }])
            ->orderBy('name')
            ->get();

        return $departments->map(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
                'employees_count' => $department->employees_count,
            ];
        })->toArray();
    }

    /**
     * Calculate percentage change between two values.
     *
     * @return float|null Returns null if previous value is 0 (no baseline to compare)
     */
    protected function calculatePercentageChange(int $previousValue, int $currentValue): ?float
    {
        if ($previousValue === 0) {
            return $currentValue > 0 ? 100.0 : null;
        }

        return round((($currentValue - $previousValue) / $previousValue) * 100, 1);
    }

    /**
     * Calculate average tenure in years for active employees.
     */
    protected function calculateAverageTenure(): float
    {
        $activeEmployees = Employee::query()
            ->active()
            ->whereNotNull('hire_date')
            ->get();

        if ($activeEmployees->isEmpty()) {
            return 0;
        }

        $totalYears = $activeEmployees->sum(function ($employee) {
            return $employee->years_of_service;
        });

        return round($totalYears / $activeEmployees->count(), 1);
    }
}
