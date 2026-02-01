<?php

namespace App\Services;

use App\Enums\ComplianceRuleType;
use App\Models\ComplianceAssignmentRule;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Service for evaluating compliance assignment rules.
 *
 * Determines which rules apply to which employees based on
 * their attributes (department, position, job level, etc.).
 */
class ComplianceRuleEngine
{
    /**
     * Evaluate if a specific rule applies to an employee.
     */
    public function evaluateRule(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        if (! $rule->is_active || ! $rule->isEffective()) {
            return false;
        }

        return $this->checkConditions($rule, $employee);
    }

    /**
     * Get all active rules that match an employee.
     *
     * @return Collection<ComplianceAssignmentRule>
     */
    public function getMatchingRules(Employee $employee): Collection
    {
        $rules = ComplianceAssignmentRule::query()
            ->active()
            ->effectiveOn()
            ->byPriority()
            ->get();

        return $rules->filter(function (ComplianceAssignmentRule $rule) use ($employee) {
            return $this->evaluateRule($rule, $employee);
        });
    }

    /**
     * Get all employees that match a specific rule.
     *
     * @return Collection<Employee>
     */
    public function getAffectedEmployees(ComplianceAssignmentRule $rule): Collection
    {
        if (! $rule->is_active || ! $rule->isEffective()) {
            return collect();
        }

        $query = Employee::query()->active();

        return match ($rule->rule_type) {
            ComplianceRuleType::AllEmployees => $query->get(),
            ComplianceRuleType::Department => $this->getEmployeesByDepartment($query, $rule),
            ComplianceRuleType::Position => $this->getEmployeesByPosition($query, $rule),
            ComplianceRuleType::JobLevel => $this->getEmployeesByJobLevel($query, $rule),
            ComplianceRuleType::WorkLocation => $this->getEmployeesByWorkLocation($query, $rule),
            ComplianceRuleType::EmploymentType => $this->getEmployeesByEmploymentType($query, $rule),
        };
    }

    /**
     * Check if rule conditions match an employee.
     */
    protected function checkConditions(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        return match ($rule->rule_type) {
            ComplianceRuleType::AllEmployees => true,
            ComplianceRuleType::Department => $this->matchesDepartment($rule, $employee),
            ComplianceRuleType::Position => $this->matchesPosition($rule, $employee),
            ComplianceRuleType::JobLevel => $this->matchesJobLevel($rule, $employee),
            ComplianceRuleType::WorkLocation => $this->matchesWorkLocation($rule, $employee),
            ComplianceRuleType::EmploymentType => $this->matchesEmploymentType($rule, $employee),
        };
    }

    /**
     * Check if employee matches department condition.
     */
    protected function matchesDepartment(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        $conditions = $rule->conditions ?? [];
        $departmentIds = $conditions['department_ids'] ?? [];

        if (empty($departmentIds)) {
            return false;
        }

        return in_array($employee->department_id, $departmentIds, false);
    }

    /**
     * Check if employee matches position condition.
     */
    protected function matchesPosition(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        $conditions = $rule->conditions ?? [];
        $positionIds = $conditions['position_ids'] ?? [];

        if (empty($positionIds)) {
            return false;
        }

        return in_array($employee->position_id, $positionIds, false);
    }

    /**
     * Check if employee matches job level condition.
     */
    protected function matchesJobLevel(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        $conditions = $rule->conditions ?? [];
        $jobLevels = $conditions['job_levels'] ?? [];

        if (empty($jobLevels)) {
            return false;
        }

        // Get job level from position
        $position = $employee->position;
        if (! $position) {
            return false;
        }

        $employeeJobLevel = $position->job_level ?? null;

        // Handle enum values
        if (is_object($employeeJobLevel) && method_exists($employeeJobLevel, 'value')) {
            $employeeJobLevel = $employeeJobLevel->value;
        }

        return in_array($employeeJobLevel, $jobLevels, false);
    }

    /**
     * Check if employee matches work location condition.
     */
    protected function matchesWorkLocation(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        $conditions = $rule->conditions ?? [];
        $workLocationIds = $conditions['work_location_ids'] ?? [];

        if (empty($workLocationIds)) {
            return false;
        }

        return in_array($employee->work_location_id, $workLocationIds, false);
    }

    /**
     * Check if employee matches employment type condition.
     */
    protected function matchesEmploymentType(ComplianceAssignmentRule $rule, Employee $employee): bool
    {
        $conditions = $rule->conditions ?? [];
        $employmentTypes = $conditions['employment_types'] ?? [];

        if (empty($employmentTypes)) {
            return false;
        }

        $employeeType = $employee->employment_type;

        // Handle enum values
        if (is_object($employeeType) && method_exists($employeeType, 'value')) {
            $employeeType = $employeeType->value;
        }

        return in_array($employeeType, $employmentTypes, false);
    }

    /**
     * Get employees by department IDs.
     *
     * @return Collection<Employee>
     */
    protected function getEmployeesByDepartment($query, ComplianceAssignmentRule $rule): Collection
    {
        $conditions = $rule->conditions ?? [];
        $departmentIds = $conditions['department_ids'] ?? [];

        if (empty($departmentIds)) {
            return collect();
        }

        return $query->whereIn('department_id', $departmentIds)->get();
    }

    /**
     * Get employees by position IDs.
     *
     * @return Collection<Employee>
     */
    protected function getEmployeesByPosition($query, ComplianceAssignmentRule $rule): Collection
    {
        $conditions = $rule->conditions ?? [];
        $positionIds = $conditions['position_ids'] ?? [];

        if (empty($positionIds)) {
            return collect();
        }

        return $query->whereIn('position_id', $positionIds)->get();
    }

    /**
     * Get employees by job level.
     *
     * @return Collection<Employee>
     */
    protected function getEmployeesByJobLevel($query, ComplianceAssignmentRule $rule): Collection
    {
        $conditions = $rule->conditions ?? [];
        $jobLevels = $conditions['job_levels'] ?? [];

        if (empty($jobLevels)) {
            return collect();
        }

        return $query->whereHas('position', function ($q) use ($jobLevels) {
            $q->whereIn('job_level', $jobLevels);
        })->get();
    }

    /**
     * Get employees by work location IDs.
     *
     * @return Collection<Employee>
     */
    protected function getEmployeesByWorkLocation($query, ComplianceAssignmentRule $rule): Collection
    {
        $conditions = $rule->conditions ?? [];
        $workLocationIds = $conditions['work_location_ids'] ?? [];

        if (empty($workLocationIds)) {
            return collect();
        }

        return $query->whereIn('work_location_id', $workLocationIds)->get();
    }

    /**
     * Get employees by employment type.
     *
     * @return Collection<Employee>
     */
    protected function getEmployeesByEmploymentType($query, ComplianceAssignmentRule $rule): Collection
    {
        $conditions = $rule->conditions ?? [];
        $employmentTypes = $conditions['employment_types'] ?? [];

        if (empty($employmentTypes)) {
            return collect();
        }

        return $query->whereIn('employment_type', $employmentTypes)->get();
    }

    /**
     * Get the count of employees that would be affected by a rule.
     */
    public function getAffectedEmployeeCount(ComplianceAssignmentRule $rule): int
    {
        return $this->getAffectedEmployees($rule)->count();
    }

    /**
     * Preview which employees would be assigned by a rule configuration.
     *
     * @param  array<string, mixed>  $conditions
     * @return Collection<Employee>
     */
    public function previewRuleEmployees(ComplianceRuleType $ruleType, array $conditions): Collection
    {
        $tempRule = new ComplianceAssignmentRule([
            'rule_type' => $ruleType,
            'conditions' => $conditions,
            'is_active' => true,
        ]);

        $query = Employee::query()->active();

        return match ($ruleType) {
            ComplianceRuleType::AllEmployees => $query->get(),
            ComplianceRuleType::Department => $this->getEmployeesByDepartment($query, $tempRule),
            ComplianceRuleType::Position => $this->getEmployeesByPosition($query, $tempRule),
            ComplianceRuleType::JobLevel => $this->getEmployeesByJobLevel($query, $tempRule),
            ComplianceRuleType::WorkLocation => $this->getEmployeesByWorkLocation($query, $tempRule),
            ComplianceRuleType::EmploymentType => $this->getEmployeesByEmploymentType($query, $tempRule),
        };
    }
}
