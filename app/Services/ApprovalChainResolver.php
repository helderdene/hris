<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Service for resolving the approval chain for leave applications.
 *
 * Implements a role-based two-step approval flow:
 *   Level 1: Department Head (per-department, via departments.department_head_id)
 *   Level 2: Admin Manager (tenant-wide, via employees.is_leave_admin_manager)
 *
 * Approvers that are inactive, the applicant themselves, or duplicate
 * across levels are skipped, collapsing the chain as needed.
 */
class ApprovalChainResolver
{
    /**
     * Resolve the approval chain for an employee.
     *
     * @param  int  $maxLevels  Hard cap on returned levels (default: 2).
     * @return Collection<int, array{employee: Employee, type: string, level: int}>
     */
    public function resolveChain(Employee $employee, int $maxLevels = 2): Collection
    {
        $approvers = collect();
        $seenIds = [$employee->id];

        $departmentHead = $this->resolveDepartmentHead($employee);
        if ($departmentHead && ! in_array($departmentHead->id, $seenIds, true)) {
            $approvers->push([
                'employee' => $departmentHead,
                'type' => 'department_head',
            ]);
            $seenIds[] = $departmentHead->id;
        }

        $adminManager = $this->resolveAdminManager($employee);
        if ($adminManager && ! in_array($adminManager->id, $seenIds, true)) {
            $approvers->push([
                'employee' => $adminManager,
                'type' => 'admin_manager',
            ]);
            $seenIds[] = $adminManager->id;
        }

        return $approvers
            ->take($maxLevels)
            ->values()
            ->map(fn (array $entry, int $index) => [
                'employee' => $entry['employee'],
                'type' => $entry['type'],
                'level' => $index + 1,
            ]);
    }

    /**
     * Resolve the Department Head for the applicant's department.
     *
     * Returns null if the department is missing, the head is unset, the head
     * is inactive, or the head is the applicant themselves.
     */
    public function resolveDepartmentHead(Employee $employee): ?Employee
    {
        if (! $employee->department_id) {
            return null;
        }

        $department = Department::query()
            ->with(['departmentHead' => fn ($query) => $query->where('employment_status', EmploymentStatus::Active),
            ])
            ->find($employee->department_id);

        $head = $department?->departmentHead;

        if (! $head || $head->id === $employee->id) {
            return null;
        }

        return $head;
    }

    /**
     * Resolve the tenant-wide Admin Manager for leave approvals.
     *
     * Returns null if no employee is flagged, the flagged employee is
     * inactive, or the flagged employee is the applicant.
     */
    public function resolveAdminManager(Employee $employee): ?Employee
    {
        $manager = Employee::query()
            ->where('is_leave_admin_manager', true)
            ->where('employment_status', EmploymentStatus::Active)
            ->where('id', '!=', $employee->id)
            ->orderBy('id')
            ->first();

        return $manager;
    }

    /**
     * Get the first available approver for an employee.
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
        if ($approver->id === $applicant->id) {
            return false;
        }

        $chain = $this->resolveChain($applicant);

        return $chain->contains(fn ($item) => $item['employee']->id === $approver->id);
    }

    /**
     * Get a fallback approver when neither role is configured.
     *
     * Used by LeaveApplicationService when resolveChain() returns empty —
     * keeps submission unblocked until the tenant assigns a Department Head
     * or Admin Manager.
     */
    public function getFallbackApprover(Employee $employee): ?Employee
    {
        return $this->resolveDepartmentHead($employee)
            ?? $this->resolveAdminManager($employee);
    }
}
