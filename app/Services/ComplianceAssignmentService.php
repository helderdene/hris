<?php

namespace App\Services;

use App\Enums\ComplianceAssignmentStatus;
use App\Enums\ComplianceProgressStatus;
use App\Events\ComplianceAssignmentCreated;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceAssignmentRule;
use App\Models\ComplianceCourse;
use App\Models\ComplianceProgress;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing compliance training assignments.
 *
 * Handles manual assignment, rule-based assignment, exemptions,
 * and assignment lifecycle management.
 */
class ComplianceAssignmentService
{
    public function __construct(
        protected ComplianceRuleEngine $ruleEngine
    ) {}

    /**
     * Manually assign a compliance course to an employee.
     */
    public function assignToEmployee(
        ComplianceCourse $complianceCourse,
        Employee $employee,
        ?Employee $assignedBy = null,
        ?int $daysToComplete = null
    ): ComplianceAssignment {
        return DB::transaction(function () use ($complianceCourse, $employee, $assignedBy, $daysToComplete) {
            // Check for existing active assignment
            $existingAssignment = ComplianceAssignment::query()
                ->where('compliance_course_id', $complianceCourse->id)
                ->where('employee_id', $employee->id)
                ->active()
                ->first();

            if ($existingAssignment) {
                return $existingAssignment;
            }

            $days = $daysToComplete ?? $complianceCourse->days_to_complete;
            $assignedDate = now()->toDateString();
            $dueDate = now()->addDays($days)->toDateString();

            $assignment = ComplianceAssignment::create([
                'compliance_course_id' => $complianceCourse->id,
                'employee_id' => $employee->id,
                'status' => ComplianceAssignmentStatus::Pending,
                'assigned_date' => $assignedDate,
                'due_date' => $dueDate,
                'assigned_by' => $assignedBy?->id,
            ]);

            // Create progress records for each module
            $this->initializeProgress($assignment);

            // Dispatch event
            event(new ComplianceAssignmentCreated($assignment));

            return $assignment;
        });
    }

    /**
     * Assign a compliance course based on a specific rule.
     */
    public function assignByRule(
        ComplianceAssignmentRule $rule,
        Employee $employee
    ): ?ComplianceAssignment {
        if (! $rule->appliesToEmployee($employee)) {
            return null;
        }

        $days = $rule->getDaysToComplete();

        return DB::transaction(function () use ($rule, $employee, $days) {
            // Check for existing active assignment
            $existingAssignment = ComplianceAssignment::query()
                ->where('compliance_course_id', $rule->compliance_course_id)
                ->where('employee_id', $employee->id)
                ->active()
                ->first();

            if ($existingAssignment) {
                return $existingAssignment;
            }

            $assignedDate = now()->toDateString();
            $dueDate = now()->addDays($days)->toDateString();

            $assignment = ComplianceAssignment::create([
                'compliance_course_id' => $rule->compliance_course_id,
                'employee_id' => $employee->id,
                'assignment_rule_id' => $rule->id,
                'status' => ComplianceAssignmentStatus::Pending,
                'assigned_date' => $assignedDate,
                'due_date' => $dueDate,
            ]);

            // Create progress records for each module
            $this->initializeProgress($assignment);

            // Dispatch event
            event(new ComplianceAssignmentCreated($assignment));

            return $assignment;
        });
    }

    /**
     * Evaluate all active rules and assign training for an employee.
     *
     * @return Collection<ComplianceAssignment>
     */
    public function evaluateRulesForEmployee(Employee $employee): Collection
    {
        $matchingRules = $this->ruleEngine->getMatchingRules($employee);
        $assignments = collect();

        foreach ($matchingRules as $rule) {
            $assignment = $this->assignByRule($rule, $employee);
            if ($assignment) {
                $assignments->push($assignment);
            }
        }

        return $assignments;
    }

    /**
     * Process compliance assignments for a new hire.
     *
     * @return Collection<ComplianceAssignment>
     */
    public function processNewHire(Employee $employee): Collection
    {
        $rules = ComplianceAssignmentRule::query()
            ->active()
            ->forNewHires()
            ->effectiveOn()
            ->byPriority()
            ->get();

        $assignments = collect();

        foreach ($rules as $rule) {
            if ($rule->appliesToEmployee($employee)) {
                $assignment = $this->assignByRule($rule, $employee);
                if ($assignment) {
                    $assignments->push($assignment);
                }
            }
        }

        return $assignments;
    }

    /**
     * Process compliance assignments when employee transfers departments.
     *
     * @return Collection<ComplianceAssignment>
     */
    public function processTransfer(Employee $employee): Collection
    {
        return $this->evaluateRulesForEmployee($employee);
    }

    /**
     * Process compliance assignments when employee changes positions.
     *
     * @return Collection<ComplianceAssignment>
     */
    public function processRoleChange(Employee $employee): Collection
    {
        return $this->evaluateRulesForEmployee($employee);
    }

    /**
     * Exempt an employee from a compliance assignment.
     */
    public function exemptEmployee(
        ComplianceAssignment $assignment,
        Employee $exemptedBy,
        string $reason
    ): ComplianceAssignment {
        $assignment->exempt($exemptedBy, $reason);

        return $assignment->fresh();
    }

    /**
     * Revoke an exemption and reactivate an assignment.
     */
    public function revokeExemption(
        ComplianceAssignment $assignment,
        ?int $newDaysToComplete = null
    ): ComplianceAssignment {
        if ($assignment->status !== ComplianceAssignmentStatus::Exempted) {
            return $assignment;
        }

        $days = $newDaysToComplete ?? $assignment->complianceCourse->days_to_complete;

        $assignment->update([
            'status' => ComplianceAssignmentStatus::Pending,
            'due_date' => now()->addDays($days)->toDateString(),
            'exemption_reason' => null,
            'exempted_by' => null,
            'exempted_at' => null,
        ]);

        return $assignment->fresh();
    }

    /**
     * Initialize progress records for all modules in an assignment.
     */
    protected function initializeProgress(ComplianceAssignment $assignment): void
    {
        $modules = $assignment->complianceCourse->modules;

        foreach ($modules as $module) {
            ComplianceProgress::create([
                'compliance_assignment_id' => $assignment->id,
                'compliance_module_id' => $module->id,
                'status' => ComplianceProgressStatus::NotStarted,
            ]);
        }
    }

    /**
     * Bulk assign a compliance course to multiple employees.
     *
     * @param  array<int>  $employeeIds
     * @return Collection<ComplianceAssignment>
     */
    public function bulkAssign(
        ComplianceCourse $complianceCourse,
        array $employeeIds,
        ?Employee $assignedBy = null,
        ?int $daysToComplete = null
    ): Collection {
        $assignments = collect();

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if ($employee) {
                $assignment = $this->assignToEmployee(
                    $complianceCourse,
                    $employee,
                    $assignedBy,
                    $daysToComplete
                );
                $assignments->push($assignment);
            }
        }

        return $assignments;
    }

    /**
     * Reassign an expired or completed assignment (for recertification).
     */
    public function reassign(
        ComplianceAssignment $originalAssignment,
        ?int $daysToComplete = null
    ): ComplianceAssignment {
        return $this->assignToEmployee(
            $originalAssignment->complianceCourse,
            $originalAssignment->employee,
            null,
            $daysToComplete
        );
    }
}
