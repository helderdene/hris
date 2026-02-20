<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelfServiceClockRequest;
use App\Models\Employee;
use App\Services\Kiosk\KioskClockService;
use App\Services\Kiosk\LocationVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfServiceClockController extends Controller
{
    /**
     * Record a self-service clock event.
     */
    public function clock(SelfServiceClockRequest $request, KioskClockService $clockService, LocationVerificationService $locationService): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $workLocation = $employee->workLocation;

        if (! $workLocation || ! $workLocation->self_service_clockin_enabled) {
            return response()->json(['message' => 'Self-service clock-in is not enabled for your work location.'], 403);
        }

        // Verify location if configured
        $gpsData = $request->only(['latitude', 'longitude', 'accuracy']);
        $verification = $locationService->verify($request, $workLocation, ! empty($gpsData) ? $gpsData : null);

        if (! $verification['passed']) {
            return response()->json(['message' => $verification['reason']], 422);
        }

        // Check cooldown (5 minutes default)
        if ($clockService->checkCooldown($employee, 5)) {
            return response()->json(['message' => 'Please wait a few minutes between clock events.'], 422);
        }

        $log = $clockService->clockSelfService($employee, $request->validated('direction'));

        return response()->json([
            'message' => 'Clock '.($log->direction === 'in' ? 'In' : 'Out').' recorded successfully.',
            'logged_at' => $log->logged_at?->toISOString(),
            'logged_at_human' => $log->logged_at?->format('g:i A'),
            'direction' => $log->direction,
        ]);
    }

    /**
     * Get the current clock status for the authenticated employee.
     */
    public function status(Request $request, KioskClockService $clockService): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (! $employee) {
            return response()->json(['message' => 'No employee record found.'], 404);
        }

        $workLocation = $employee->workLocation;
        $lastPunch = $clockService->getLastPunch($employee);

        return response()->json([
            'self_service_enabled' => $workLocation?->self_service_clockin_enabled ?? false,
            'location_check' => $workLocation?->location_check ?? 'none',
            'last_punch' => $lastPunch ? [
                'direction' => $lastPunch->direction,
                'logged_at' => $lastPunch->logged_at?->toISOString(),
                'logged_at_human' => $lastPunch->logged_at?->diffForHumans(),
            ] : null,
            'suggested_direction' => $lastPunch?->direction === 'in' ? 'out' : 'in',
        ]);
    }
}
