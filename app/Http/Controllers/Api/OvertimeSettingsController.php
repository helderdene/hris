<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetOvertimeApproversRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class OvertimeSettingsController extends Controller
{
    /**
     * Replace the set of level 2 overtime approvers.
     *
     * Any one of the flagged employees may approve at level 2. Pass an
     * empty list to fall back to the Leave Admin Manager.
     */
    public function setApprovers(SetOvertimeApproversRequest $request): JsonResponse
    {
        $employeeIds = $request->validated('employee_ids');

        Employee::query()
            ->where('is_overtime_approver', true)
            ->whereNotIn('id', $employeeIds)
            ->update(['is_overtime_approver' => false]);

        Employee::query()
            ->whereKey($employeeIds)
            ->update(['is_overtime_approver' => true]);

        return response()->json([
            'message' => $employeeIds === []
                ? 'Overtime approvers cleared.'
                : 'Overtime approvers updated.',
            'approver_ids' => Employee::query()->where('is_overtime_approver', true)->pluck('id'),
        ]);
    }
}
