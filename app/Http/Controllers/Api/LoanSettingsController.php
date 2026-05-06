<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LoanSettingsController extends Controller
{
    /**
     * Set (or clear) the three loan approval role-holders.
     *
     * Pass any role's employee_id as null to remove the holder for that role.
     */
    public function setRoles(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $rules = [
            'nullable',
            'integer',
            Rule::exists(Employee::class, 'id')
                ->where('employment_status', EmploymentStatus::Active->value),
        ];

        $validated = $request->validate([
            'cfo_employee_id' => $rules,
            'admin_manager_employee_id' => $rules,
            'releasing_officer_employee_id' => $rules,
        ], [
            '*.exists' => 'The selected employee must be active.',
        ]);

        $assignments = [
            'is_loan_cfo' => $validated['cfo_employee_id'] ?? null,
            'is_loan_admin_manager' => $validated['admin_manager_employee_id'] ?? null,
            'is_loan_releasing_officer' => $validated['releasing_officer_employee_id'] ?? null,
        ];

        foreach ($assignments as $flag => $employeeId) {
            Employee::query()->where($flag, true)->update([$flag => false]);

            if ($employeeId) {
                Employee::query()->whereKey($employeeId)->update([$flag => true]);
            }
        }

        return response()->json([
            'message' => 'Loan approval roles updated.',
            'roles' => [
                'cfo' => $this->formatRoleHolder('is_loan_cfo'),
                'admin_manager' => $this->formatRoleHolder('is_loan_admin_manager'),
                'releasing_officer' => $this->formatRoleHolder('is_loan_releasing_officer'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function formatRoleHolder(string $flag): ?array
    {
        $employee = Employee::query()
            ->where($flag, true)
            ->with(['department', 'position'])
            ->first();

        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
        ];
    }
}
