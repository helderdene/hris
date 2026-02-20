<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Http\Requests\KioskClockRequest;
use App\Http\Requests\VerifyKioskPinRequest;
use App\Models\Kiosk;
use App\Models\VisitorVisit;
use App\Services\Kiosk\KioskClockService;
use App\Services\Kiosk\KioskPinService;
use App\Services\Kiosk\LocationVerificationService;
use App\Services\Visitor\VisitorCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class KioskTerminalController extends Controller
{
    /**
     * Display the kiosk terminal.
     */
    public function show(string $token, LocationVerificationService $locationService): Response|JsonResponse
    {
        $kiosk = Kiosk::where('token', $token)->firstOrFail();

        if (! $kiosk->is_active) {
            abort(404, 'This kiosk is not active.');
        }

        // Check IP whitelist if configured
        if (! empty($kiosk->ip_whitelist)) {
            if (! $locationService->verifyIp(request()->ip(), $kiosk->ip_whitelist)) {
                abort(403, 'Access denied from this IP address.');
            }
        }

        return Inertia::render('Kiosk/Terminal', [
            'kiosk' => [
                'name' => $kiosk->name,
                'location' => $kiosk->location,
                'token' => $kiosk->token,
                'cooldown_minutes' => $kiosk->getCooldownMinutes(),
            ],
            'companyName' => tenant()?->name,
            'companyLogo' => tenant()?->logo_path,
        ]);
    }

    /**
     * Verify an employee PIN.
     */
    public function verifyPin(VerifyKioskPinRequest $request, string $token, KioskPinService $pinService, KioskClockService $clockService): JsonResponse
    {
        $kiosk = Kiosk::where('token', $token)->where('is_active', true)->firstOrFail();

        // Rate limit: 5 attempts per minute per IP
        $key = 'kiosk-pin:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in a moment.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $employee = $pinService->verifyPin($request->validated('pin'));

        if (! $employee) {
            return response()->json([
                'message' => 'Invalid PIN. Please try again.',
            ], 422);
        }

        $lastPunch = $clockService->getLastPunch($employee);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
            ],
            'last_punch' => $lastPunch ? [
                'direction' => $lastPunch->direction,
                'logged_at' => $lastPunch->logged_at?->toISOString(),
                'logged_at_human' => $lastPunch->logged_at?->diffForHumans(),
            ] : null,
            'suggested_direction' => $lastPunch?->direction === 'in' ? 'out' : 'in',
        ]);
    }

    /**
     * Record a clock event.
     */
    public function clock(KioskClockRequest $request, string $token, KioskClockService $clockService): JsonResponse
    {
        $kiosk = Kiosk::where('token', $token)->where('is_active', true)->firstOrFail();

        $employee = \App\Models\Employee::findOrFail($request->validated('employee_id'));

        // Check cooldown
        if ($clockService->checkCooldown($employee, $kiosk->getCooldownMinutes())) {
            return response()->json([
                'message' => "Please wait {$kiosk->getCooldownMinutes()} minutes between clock events.",
            ], 422);
        }

        $log = $clockService->clock($employee, $request->validated('direction'), $kiosk);

        return response()->json([
            'message' => 'Clock '.($request->validated('direction') === 'in' ? 'In' : 'Out').' recorded successfully.',
            'logged_at' => $log->logged_at?->toISOString(),
            'logged_at_human' => $log->logged_at?->format('g:i A'),
            'direction' => $log->direction,
        ]);
    }

    /**
     * Check in a visitor via QR code scanned at kiosk.
     */
    public function visitorCheckIn(Request $request, string $token, VisitorCheckInService $checkInService): JsonResponse
    {
        $kiosk = Kiosk::where('token', $token)->where('is_active', true)->firstOrFail();

        // Rate limit: 5 attempts per minute per IP
        $key = 'kiosk-visitor:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again in a moment.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $request->validate([
            'qr_token' => ['required', 'string', 'max:64'],
        ]);

        $visit = VisitorVisit::where('qr_token', $request->input('qr_token'))
            ->with('visitor')
            ->first();

        if (! $visit) {
            return response()->json([
                'message' => 'Invalid QR code. Please try again.',
            ], 422);
        }

        // If already checked in, trigger check-out
        if ($visit->status === VisitStatus::CheckedIn) {
            $visit = $checkInService->checkOut($visit);

            return response()->json([
                'message' => 'Visitor checked out successfully.',
                'visitor_name' => $visit->visitor->full_name,
                'action' => 'check_out',
                'checked_out_at' => $visit->checked_out_at?->format('g:i A'),
            ]);
        }

        // Check in
        if (! in_array($visit->status, [VisitStatus::Approved, VisitStatus::PreRegistered])) {
            return response()->json([
                'message' => 'This visit is not eligible for check-in.',
            ], 422);
        }

        $visit = $checkInService->checkInViaKiosk($visit, $kiosk);

        return response()->json([
            'message' => 'Welcome! You have been checked in.',
            'visitor_name' => $visit->visitor->full_name,
            'action' => 'check_in',
            'checked_in_at' => $visit->checked_in_at?->format('g:i A'),
        ]);
    }

    /**
     * Explicitly check out a visitor via kiosk.
     */
    public function visitorCheckOut(Request $request, string $token, VisitorCheckInService $checkInService): JsonResponse
    {
        $kiosk = Kiosk::where('token', $token)->where('is_active', true)->firstOrFail();

        $request->validate([
            'qr_token' => ['required', 'string', 'max:64'],
        ]);

        $visit = VisitorVisit::where('qr_token', $request->input('qr_token'))
            ->where('status', VisitStatus::CheckedIn)
            ->with('visitor')
            ->first();

        if (! $visit) {
            return response()->json([
                'message' => 'No active visit found for this QR code.',
            ], 422);
        }

        $visit = $checkInService->checkOut($visit);

        return response()->json([
            'message' => 'Visitor checked out successfully.',
            'visitor_name' => $visit->visitor->full_name,
            'checked_out_at' => $visit->checked_out_at?->format('g:i A'),
        ]);
    }
}
