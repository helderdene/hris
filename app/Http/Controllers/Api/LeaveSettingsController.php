<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaveSettingsController extends Controller
{
    /**
     * Set (or clear) the tenant-wide Leave Admin Manager.
     *
     * Pass employee_id = null to remove the current Admin Manager.
     */
    public function setAdminManager(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists(Employee::class, 'id')
                    ->where('employment_status', EmploymentStatus::Active->value),
            ],
        ], [
            'employee_id.exists' => 'The selected employee must be active.',
        ]);

        Employee::query()
            ->where('is_leave_admin_manager', true)
            ->update(['is_leave_admin_manager' => false]);

        if (! empty($validated['employee_id'])) {
            Employee::query()
                ->whereKey($validated['employee_id'])
                ->update(['is_leave_admin_manager' => true]);
        }

        $manager = Employee::query()
            ->where('is_leave_admin_manager', true)
            ->with(['department', 'position'])
            ->first();

        return response()->json([
            'message' => $manager
                ? 'Leave Admin Manager updated.'
                : 'Leave Admin Manager cleared.',
            'admin_manager' => $manager ? $this->formatEmployee($manager) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
        ];
    }
}
