<?php

namespace App\Services;

use App\Enums\AccrualMethod;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveBalanceAdjustmentType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing leave balances.
 *
 * Handles balance initialization, accrual processing, year-end carry-over,
 * manual adjustments, and balance reservation for leave requests.
 */
class LeaveBalanceService
{
    /**
     * Initialize leave balances for an employee for a specific year.
     *
     * Creates balance records for all eligible leave types based on the
     * employee's employment type, tenure, and gender restrictions.
     *
     * @return Collection<int, LeaveBalance>
     */
    public function initializeBalancesForEmployee(Employee $employee, int $year): Collection
    {
        $balances = collect();

        $leaveTypes = LeaveType::query()->active()->get();

        foreach ($leaveTypes as $leaveType) {
            if (! $leaveType->isEmployeeEligible($employee)) {
                continue;
            }

            // Check if balance already exists
            $existingBalance = LeaveBalance::query()
                ->forEmployee($employee)
                ->forLeaveType($leaveType)
                ->forYear($year)
                ->first();

            if ($existingBalance !== null) {
                $balances->push($existingBalance);

                continue;
            }

            // Calculate initial entitlement based on accrual method
            $initialEarned = $this->calculateInitialEntitlement($employee, $leaveType, $year);

            $balance = LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
                'brought_forward' => 0,
                'earned' => $initialEarned,
                'used' => 0,
                'pending' => 0,
                'adjustments' => 0,
                'expired' => 0,
                'carry_over_expiry_date' => null,
                'last_accrual_at' => $leaveType->accrual_method === AccrualMethod::Annual ? now() : null,
            ]);

            $balances->push($balance);
        }

        return $balances;
    }

    /**
     * Calculate initial entitlement based on accrual method and hire date.
     */
    protected function calculateInitialEntitlement(Employee $employee, LeaveType $leaveType, int $year): float
    {
        $entitlement = $leaveType->calculateEntitlement($employee);

        // For annual accrual, give full entitlement at start of year
        if ($leaveType->accrual_method === AccrualMethod::Annual) {
            // Pro-rate for mid-year hires
            if ($employee->hire_date !== null && $employee->hire_date->year === $year) {
                $monthsRemaining = 12 - $employee->hire_date->month + 1;
                $entitlement = round($entitlement * ($monthsRemaining / 12), 2);
            }

            return $entitlement;
        }

        // For monthly accrual, start with 0 - will be accrued monthly
        if ($leaveType->accrual_method === AccrualMethod::Monthly) {
            return 0;
        }

        // For tenure-based, give full entitlement
        if ($leaveType->accrual_method === AccrualMethod::TenureBased) {
            return $entitlement;
        }

        // For 'none' accrual method (e.g., maternity leave), give full entitlement
        return $entitlement;
    }

    /**
     * Process monthly accrual for all active employees.
     *
     * Should be called on the 1st of each month.
     *
     * @return array{processed: int, skipped: int}
     */
    public function processMonthlyAccrualForAllEmployees(): array
    {
        $year = now()->year;
        $month = now()->month;
        $processed = 0;
        $skipped = 0;

        // Get all leave types with monthly accrual
        $monthlyLeaveTypes = LeaveType::query()
            ->active()
            ->where('accrual_method', AccrualMethod::Monthly)
            ->get();

        if ($monthlyLeaveTypes->isEmpty()) {
            return ['processed' => 0, 'skipped' => 0];
        }

        // Get all active employees
        $employees = Employee::query()->active()->get();

        foreach ($employees as $employee) {
            foreach ($monthlyLeaveTypes as $leaveType) {
                if (! $leaveType->isEmployeeEligible($employee)) {
                    $skipped++;

                    continue;
                }

                $balance = LeaveBalance::query()
                    ->forEmployee($employee)
                    ->forLeaveType($leaveType)
                    ->forYear($year)
                    ->first();

                // Initialize if not exists
                if ($balance === null) {
                    $balance = LeaveBalance::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                        'brought_forward' => 0,
                        'earned' => 0,
                        'used' => 0,
                        'pending' => 0,
                        'adjustments' => 0,
                        'expired' => 0,
                    ]);
                }

                // Check if already accrued this month
                if ($balance->last_accrual_at !== null) {
                    $lastAccrualMonth = $balance->last_accrual_at->month;
                    $lastAccrualYear = $balance->last_accrual_at->year;

                    if ($lastAccrualYear === $year && $lastAccrualMonth === $month) {
                        $skipped++;

                        continue;
                    }
                }

                // Calculate monthly accrual
                $monthlyRate = $leaveType->monthly_accrual_rate ?? ($leaveType->default_days_per_year / 12);
                $balance->earned = (float) $balance->earned + $monthlyRate;
                $balance->last_accrual_at = now();
                $balance->save();

                $processed++;
            }
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * Process year-end: carry-over, forfeiture, and new year initialization.
     *
     * @param  int  $fromYear  The year that is ending
     * @return array{carried_over: int, forfeited: int, initialized: int}
     */
    public function processYearEnd(int $fromYear): array
    {
        $toYear = $fromYear + 1;
        $carriedOver = 0;
        $forfeited = 0;
        $initialized = 0;

        // Get all balances from the ending year
        $balances = LeaveBalance::query()
            ->forYear($fromYear)
            ->whereNull('year_end_processed_at')
            ->with(['employee', 'leaveType'])
            ->get();

        foreach ($balances as $balance) {
            if ($balance->employee === null || $balance->leaveType === null) {
                continue;
            }

            // Skip inactive employees
            if ($balance->employee->employment_status !== EmploymentStatus::Active) {
                $balance->year_end_processed_at = now();
                $balance->save();

                continue;
            }

            $leaveType = $balance->leaveType;

            // Calculate unused balance
            $unusedBalance = (float) $balance->brought_forward
                + (float) $balance->earned
                + (float) $balance->adjustments
                - (float) $balance->used
                - (float) $balance->expired;

            // Process carry-over
            $carryOver = 0;
            $forfeit = 0;
            $expiryDate = null;

            if ($leaveType->allow_carry_over && $unusedBalance > 0) {
                // Apply max carry-over limit
                $maxCarryOver = $leaveType->max_carry_over_days ?? $unusedBalance;
                $carryOver = min($unusedBalance, $maxCarryOver);
                $forfeit = $unusedBalance - $carryOver;

                // Calculate expiry date
                if ($leaveType->carry_over_expiry_months !== null && $carryOver > 0) {
                    $expiryDate = Carbon::create($toYear, 1, 1)
                        ->addMonths($leaveType->carry_over_expiry_months)
                        ->subDay()
                        ->toDateString();
                }
            } else {
                // No carry-over allowed - forfeit everything
                $forfeit = max(0, $unusedBalance);
            }

            // Mark original balance as processed
            $balance->year_end_processed_at = now();
            $balance->save();

            // Check if new year balance already exists
            $newYearBalance = LeaveBalance::query()
                ->forEmployee($balance->employee)
                ->forLeaveType($balance->leaveType)
                ->forYear($toYear)
                ->first();

            if ($newYearBalance !== null) {
                // Update existing balance with carry-over
                $newYearBalance->brought_forward = $carryOver;
                $newYearBalance->carry_over_expiry_date = $expiryDate;
                $newYearBalance->save();
            } else {
                // Create new year balance
                $initialEarned = $this->calculateInitialEntitlement(
                    $balance->employee,
                    $leaveType,
                    $toYear
                );

                LeaveBalance::create([
                    'employee_id' => $balance->employee_id,
                    'leave_type_id' => $balance->leave_type_id,
                    'year' => $toYear,
                    'brought_forward' => $carryOver,
                    'earned' => $initialEarned,
                    'used' => 0,
                    'pending' => 0,
                    'adjustments' => 0,
                    'expired' => 0,
                    'carry_over_expiry_date' => $expiryDate,
                    'last_accrual_at' => $leaveType->accrual_method === AccrualMethod::Annual ? now() : null,
                ]);

                $initialized++;
            }

            if ($carryOver > 0) {
                $carriedOver++;
            }

            if ($forfeit > 0) {
                $forfeited++;
            }
        }

        return [
            'carried_over' => $carriedOver,
            'forfeited' => $forfeited,
            'initialized' => $initialized,
        ];
    }

    /**
     * Record a manual adjustment to a leave balance.
     *
     * @return LeaveBalanceAdjustment The created adjustment record
     */
    public function recordAdjustment(
        LeaveBalance $balance,
        LeaveBalanceAdjustmentType $type,
        float $days,
        string $reason,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): LeaveBalanceAdjustment {
        return DB::transaction(function () use ($balance, $type, $days, $reason, $userId, $referenceType, $referenceId) {
            $previousBalance = $balance->adjustments;
            $signedDays = $days * $type->sign();
            $newBalance = (float) $previousBalance + $signedDays;

            // Update the balance
            $balance->adjustments = $newBalance;
            $balance->save();

            // Create audit record
            return LeaveBalanceAdjustment::create([
                'leave_balance_id' => $balance->id,
                'adjusted_by' => $userId,
                'adjustment_type' => $type,
                'days' => $days,
                'reason' => $reason,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Record usage when leave is approved.
     *
     * Deducts days from the balance and creates an audit trail.
     */
    public function recordUsage(Employee $employee, LeaveType $leaveType, int $year, float $days): void
    {
        $balance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear($year)
            ->first();

        if ($balance === null) {
            Log::warning('LeaveBalanceService: Balance not found for usage', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ]);

            return;
        }

        $balance->recordUsage($days);
    }

    /**
     * Reserve balance when leave request is submitted.
     */
    public function reserveBalance(Employee $employee, LeaveType $leaveType, int $year, float $days): bool
    {
        $balance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear($year)
            ->first();

        if ($balance === null) {
            return false;
        }

        if (! $balance->hasAvailableBalance($days)) {
            return false;
        }

        $balance->recordPending($days);

        return true;
    }

    /**
     * Release reserved balance when leave request is cancelled or rejected.
     */
    public function releaseReservedBalance(Employee $employee, LeaveType $leaveType, int $year, float $days): void
    {
        $balance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear($year)
            ->first();

        if ($balance === null) {
            return;
        }

        $balance->releasePending($days);
    }

    /**
     * Approve reserved balance - convert pending to used.
     */
    public function approveReservedBalance(Employee $employee, LeaveType $leaveType, int $year, float $days): void
    {
        $balance = LeaveBalance::query()
            ->forEmployee($employee)
            ->forLeaveType($leaveType)
            ->forYear($year)
            ->first();

        if ($balance === null) {
            return;
        }

        $balance->convertPendingToUsed($days);
    }

    /**
     * Expire carried-over balances that have passed their expiry date.
     *
     * @return int Number of balances processed
     */
    public function expireCarriedOverBalances(): int
    {
        $balances = LeaveBalance::query()
            ->withExpiredCarryOver()
            ->get();

        $count = 0;

        foreach ($balances as $balance) {
            $balance->expireCarryOver();
            $count++;
        }

        return $count;
    }

    /**
     * Get balance summary for an employee for a specific year.
     *
     * @return Collection<int, array{leave_type: LeaveType, balance: LeaveBalance|null, available: float}>
     */
    public function getEmployeeBalanceSummary(Employee $employee, int $year): Collection
    {
        $leaveTypes = LeaveType::query()->active()->get();
        $balances = LeaveBalance::query()
            ->forEmployee($employee)
            ->forYear($year)
            ->get()
            ->keyBy('leave_type_id');

        return $leaveTypes->map(function ($leaveType) use ($balances, $employee) {
            $balance = $balances->get($leaveType->id);

            return [
                'leave_type' => $leaveType,
                'balance' => $balance,
                'available' => $balance ? $balance->available : 0,
                'is_eligible' => $leaveType->isEmployeeEligible($employee),
            ];
        });
    }
}
