<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Service for resolving the approval chain for leave applications.
 *
 * Builds an ordered list of approvers based on the employee's supervisor hierarchy.
 */
class ApprovalChainResolver
{
    /**
     * Resolve the approval chain for an employee.
     *
     * Returns an ordered collection of employees who should approve the leave request.
     * Each element includes the employee and their approver type.
     *
     * @param  int  $maxLevels  Maximum levels of approval (default: 2)
     * @return Collection<int, array{employee: Employee, type: string, level: int}>
     */
    public function resolveChain(Employee $employee, int $maxLevels = 2): Collection
    {
        $chain = collect();
        $currentSupervisor = $employee->supervisor;
        $level = 1;
        $seenIds = [$employee->id]; // Prevent self-approval and circular references

        while ($currentSupervisor !== null && $level <= $maxLevels) {
            // Skip if we've already seen this supervisor (circular reference)
            if (in_array($currentSupervisor->id, $seenIds, true)) {
                break;
            }

            // Skip inactive supervisors
            if ($currentSupervisor->employment_status !== EmploymentStatus::Active) {
                $seenIds[] = $currentSupervisor->id;
                $currentSupervisor = $currentSupervisor->supervisor;

                continue;
            }

            $chain->push([
                'employee' => $currentSupervisor,
                'type' => $this->determineApproverType($level, $currentSupervisor),
                'level' => $level,
            ]);

            $seenIds[] = $currentSupervisor->id;
            $currentSupervisor = $currentSupervisor->supervisor;
            $level++;
        }

        return $chain;
    }

    /**
     * Get the first available approver for an employee.
     *
     * Useful for simple single-level approval workflows.
     */
    public function getFirstApprover(Employee $employee): ?Employee
    {
        $chain = $this->resolveChain($employee, 1);

        if ($chain->isEmpty()) {
            return null;
        }

        return $chain->first()['employee'];
    }

    /**
     * Check if an employee can approve for another employee.
     */
    public function canApprove(Employee $approver, Employee $applicant): bool
    {
        // Self-approval is not allowed
        if ($approver->id === $applicant->id) {
            return false;
        }

        // Check if approver is in the applicant's supervisor chain
        $chain = $this->resolveChain($applicant, 5);

        return $chain->contains(fn ($item) => $item['employee']->id === $approver->id);
    }

    /**
     * Determine the approver type based on level and position.
     */
    protected function determineApproverType(int $level, Employee $approver): string
    {
        // Level 1 is typically direct supervisor
        if ($level === 1) {
            return 'supervisor';
        }

        // Check if this is a department head
        if (in_array($approver->position?->job_level?->value, ['manager', 'director', 'executive'], true)) {
            return 'department_head';
        }

        // Level 2+ could be higher management
        if ($level === 2) {
            return 'manager';
        }

        return 'senior_manager';
    }

    /**
     * Get a fallback approver when no supervisor is available.
     *
     * Falls back to department head or HR if no direct supervisor exists.
     */
    public function getFallbackApprover(Employee $employee): ?Employee
    {
        // Try to get the department head
        if ($employee->department_id) {
            $departmentHead = Employee::query()
                ->where('department_id', $employee->department_id)
                ->where('id', '!=', $employee->id)
                ->where('employment_status', EmploymentStatus::Active)
                ->whereHas('position', fn ($q) => $q->whereIn('job_level', ['manager', 'director', 'executive']))
                ->first();

            if ($departmentHead) {
                return $departmentHead;
            }
        }

        return null;
    }
}
