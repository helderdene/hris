<?php

namespace App\Services;

use App\Enums\AssignmentType;
use App\Models\Department;
use App\Models\DepartmentHeadHistory;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Position;
use App\Models\WorkLocation;
use App\Services\Biometric\EmployeeSyncService;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    /**
     * Create a new assignment and handle ending the previous assignment atomically.
     *
     * @param  array{
     *     assignment_type: string,
     *     new_value_id: int,
     *     effective_date: string,
     *     remarks?: string|null,
     *     set_as_department_head?: bool
     * }  $data
     */
    public function createAssignment(Employee $employee, array $data, ?int $changedBy = null): EmployeeAssignmentHistory
    {
        return DB::transaction(function () use ($employee, $data, $changedBy) {
            $assignmentType = AssignmentType::from($data['assignment_type']);

            // Get the current assignment's value (to set as previous_value_id)
            $previousValueId = $this->getCurrentAssignmentValue($employee, $assignmentType);

            // End the previous assignment of the same type
            $this->endCurrentAssignment($employee, $assignmentType);

            // Create the new assignment history record
            $assignment = EmployeeAssignmentHistory::create([
                'employee_id' => $employee->id,
                'assignment_type' => $assignmentType,
                'previous_value_id' => $previousValueId,
                'new_value_id' => $data['new_value_id'],
                'effective_date' => $data['effective_date'],
                'remarks' => $data['remarks'] ?? null,
                'changed_by' => $changedBy,
                'ended_at' => null,
            ]);

            // Update the employee's current assignment field
            $this->updateEmployeeAssignment($employee, $assignmentType, $data['new_value_id']);

            // Handle department head assignment if requested
            if ($assignmentType === AssignmentType::Department && ($data['set_as_department_head'] ?? false)) {
                $this->setAsDepartmentHead($employee, $data['new_value_id'], $data['effective_date']);
            }

            // Initialize biometric sync records when work location changes
            if ($assignmentType === AssignmentType::Location) {
                $employee->refresh();
                app(EmployeeSyncService::class)->initializeSyncRecords($employee);
            }

            return $assignment;
        });
    }

    /**
     * Set an employee as the department head for a department.
     *
     * This updates both the Department model and creates a DepartmentHeadHistory record.
     */
    protected function setAsDepartmentHead(Employee $employee, int $departmentId, string $effectiveDate): void
    {
        $department = Department::findOrFail($departmentId);

        // End the current department head history record if exists
        DepartmentHeadHistory::where('department_id', $departmentId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        // Create new department head history record
        DepartmentHeadHistory::create([
            'department_id' => $departmentId,
            'employee_id' => $employee->id,
            'started_at' => $effectiveDate,
            'ended_at' => null,
        ]);

        // Update the department's current head
        $department->update(['department_head_id' => $employee->id]);
    }

    /**
     * Get the current assignment value for a specific type from the Employee model.
     */
    public function getCurrentAssignmentValue(Employee $employee, AssignmentType $type): ?int
    {
        return match ($type) {
            AssignmentType::Position => $employee->position_id,
            AssignmentType::Department => $employee->department_id,
            AssignmentType::Location => $employee->work_location_id,
            AssignmentType::Supervisor => $employee->supervisor_id,
        };
    }

    /**
     * End the current (active) assignment of a specific type for an employee.
     */
    protected function endCurrentAssignment(Employee $employee, AssignmentType $type): void
    {
        EmployeeAssignmentHistory::query()
            ->where('employee_id', $employee->id)
            ->where('assignment_type', $type)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }

    /**
     * Update the Employee model's current assignment field based on assignment type.
     */
    protected function updateEmployeeAssignment(Employee $employee, AssignmentType $type, int $newValueId): void
    {
        $field = match ($type) {
            AssignmentType::Position => 'position_id',
            AssignmentType::Department => 'department_id',
            AssignmentType::Location => 'work_location_id',
            AssignmentType::Supervisor => 'supervisor_id',
        };

        $employee->update([$field => $newValueId]);
    }

    /**
     * Resolve a value name from an ID based on the assignment type.
     */
    public function resolveValueName(?int $valueId, AssignmentType $type): ?string
    {
        if ($valueId === null) {
            return null;
        }

        return match ($type) {
            AssignmentType::Position => Position::find($valueId)?->title,
            AssignmentType::Department => Department::find($valueId)?->name,
            AssignmentType::Location => WorkLocation::find($valueId)?->name,
            AssignmentType::Supervisor => Employee::find($valueId)?->full_name,
        };
    }
}
