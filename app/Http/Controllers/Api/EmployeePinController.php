<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Kiosk\KioskPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeePinController extends Controller
{
    /**
     * Generate a new kiosk PIN for an employee.
     */
    public function generate(Employee $employee, KioskPinService $pinService): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $pin = $pinService->assignPin($employee);

        return response()->json([
            'message' => 'PIN generated successfully.',
            'pin' => $pin,
            'employee_name' => $employee->full_name,
        ]);
    }

    /**
     * Reset an employee's kiosk PIN.
     */
    public function reset(Employee $employee, KioskPinService $pinService): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $pin = $pinService->resetPin($employee);

        return response()->json([
            'message' => 'PIN has been reset.',
            'pin' => $pin,
            'employee_name' => $employee->full_name,
        ]);
    }

    /**
     * Reset the authenticated user's own PIN.
     */
    public function resetOwn(Request $request, KioskPinService $pinService): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $pin = $pinService->resetPin($employee);

        return response()->json([
            'message' => 'Your PIN has been reset.',
            'pin' => $pin,
        ]);
    }
}
