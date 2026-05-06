<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\LoanApplication;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Resolves the 3-step loan approval chain.
 *
 *   Level 1: CFO (employees.is_loan_cfo)
 *   Level 2: Admin Manager (employees.is_loan_admin_manager)
 *   Level 3: Releasing Officer (employees.is_loan_releasing_officer)
 *
 * Computes per-step calendar-day deadlines:
 *   - Standard urgency (level <= 4):     5 / 3 / 2 days
 *   - High urgency (urgency_level == 5): 1.5 / 1 / 0.5 days  (3 days total)
 *
 * Each role is filled by the lowest-id active employee carrying the flag.
 * Duplicate approvers across levels collapse to a single step.
 */
class LoanApprovalChainResolver
{
    private const STANDARD_DAYS = [1 => 5.0, 2 => 3.0, 3 => 2.0];

    private const HIGH_URGENCY_DAYS = [1 => 1.5, 2 => 1.0, 3 => 0.5];

    /**
     * Build the approval chain for an application.
     *
     * @return Collection<int, array{
     *     employee: Employee,
     *     type: string,
     *     level: int,
     *     deadline: CarbonImmutable,
     * }>
     */
    public function resolveChain(LoanApplication $application): Collection
    {
        $applicantId = $application->employee_id;
        $isHighUrgency = (int) $application->urgency_level === 5;
        $perLevelDays = $isHighUrgency ? self::HIGH_URGENCY_DAYS : self::STANDARD_DAYS;

        $startedAt = CarbonImmutable::parse($application->submitted_at ?? now());
        $cumulative = 0.0;

        $chain = collect();
        $seenIds = [$applicantId];

        $roles = [
            ['flag' => 'is_loan_cfo', 'type' => 'cfo'],
            ['flag' => 'is_loan_admin_manager', 'type' => 'admin_manager'],
            ['flag' => 'is_loan_releasing_officer', 'type' => 'releasing_officer'],
        ];

        $level = 1;
        foreach ($roles as $role) {
            $cumulative += $perLevelDays[$level];

            $approver = $this->findRoleHolder($role['flag'], $seenIds);
            if (! $approver) {
                $level++;

                continue;
            }

            $deadline = $startedAt->addRealMinutes((int) round($cumulative * 24 * 60));

            $chain->push([
                'employee' => $approver,
                'type' => $role['type'],
                'level' => $level,
                'deadline' => $deadline,
            ]);

            $seenIds[] = $approver->id;
            $level++;
        }

        // Re-number levels after de-duplication so the saved chain is dense
        // (1, 2, 3) rather than sparse if a role-holder was skipped.
        return $chain
            ->values()
            ->map(fn (array $entry, int $index) => [
                'employee' => $entry['employee'],
                'type' => $entry['type'],
                'level' => $index + 1,
                'deadline' => $entry['deadline'],
            ]);
    }

    /**
     * Compute the overall SLA deadline (sum of all per-level days) from a
     * starting timestamp.
     */
    public function computeSlaDeadline(LoanApplication $application): CarbonImmutable
    {
        $isHighUrgency = (int) $application->urgency_level === 5;
        $totalDays = $isHighUrgency
            ? array_sum(self::HIGH_URGENCY_DAYS)
            : array_sum(self::STANDARD_DAYS);

        $startedAt = CarbonImmutable::parse($application->submitted_at ?? now());

        return $startedAt->addRealMinutes((int) round($totalDays * 24 * 60));
    }

    /**
     * Find the lowest-id active employee carrying a given role flag, skipping
     * anyone whose id is already in $seenIds (applicant or earlier-level
     * approver).
     *
     * @param  array<int>  $seenIds
     */
    protected function findRoleHolder(string $flag, array $seenIds): ?Employee
    {
        return Employee::query()
            ->where($flag, true)
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotIn('id', $seenIds)
            ->orderBy('id')
            ->first();
    }
}
