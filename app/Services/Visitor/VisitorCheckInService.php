<?php

namespace App\Services\Visitor;

use App\Enums\CheckInMethod;
use App\Enums\VisitStatus;
use App\Models\BiometricDevice;
use App\Models\Kiosk;
use App\Models\User;
use App\Models\VisitorVisit;
use App\Notifications\VisitorArrived;
use App\Notifications\VisitorCheckedOut;
use Illuminate\Validation\ValidationException;

class VisitorCheckInService
{
    /**
     * Manually check in a visitor (front desk).
     */
    public function checkInManual(VisitorVisit $visit, User $user, ?string $badgeNumber = null): VisitorVisit
    {
        $this->validateCheckInStatus($visit);

        $visit->update([
            'status' => VisitStatus::CheckedIn,
            'checked_in_at' => now(),
            'check_in_method' => CheckInMethod::Manual,
            'checked_in_by' => $user->id,
            'badge_number' => $badgeNumber,
        ]);

        $this->notifyHost($visit);

        return $visit;
    }

    /**
     * Check in a visitor via kiosk QR scan.
     */
    public function checkInViaKiosk(VisitorVisit $visit, Kiosk $kiosk): VisitorVisit
    {
        $this->validateCheckInStatus($visit);

        $visit->update([
            'status' => VisitStatus::CheckedIn,
            'checked_in_at' => now(),
            'check_in_method' => CheckInMethod::Kiosk,
            'kiosk_id' => $kiosk->id,
        ]);

        $this->notifyHost($visit);

        return $visit;
    }

    /**
     * Check in a visitor via biometric (FR device).
     */
    public function checkInViaBiometric(VisitorVisit $visit, BiometricDevice $device): VisitorVisit
    {
        $this->validateCheckInStatus($visit);

        $visit->update([
            'status' => VisitStatus::CheckedIn,
            'checked_in_at' => now(),
            'check_in_method' => CheckInMethod::Biometric,
            'biometric_device_id' => $device->id,
        ]);

        $this->notifyHost($visit);

        return $visit;
    }

    /**
     * Check out a visitor.
     */
    public function checkOut(VisitorVisit $visit, ?User $user = null): VisitorVisit
    {
        if ($visit->status !== VisitStatus::CheckedIn) {
            throw ValidationException::withMessages([
                'status' => 'Only checked-in visitors can be checked out.',
            ]);
        }

        $visit->update([
            'status' => VisitStatus::CheckedOut,
            'checked_out_at' => now(),
        ]);

        // Notify host of departure
        $visit->load(['visitor', 'hostEmployee']);
        if ($visit->hostEmployee?->user) {
            $visit->hostEmployee->user->notify(new VisitorCheckedOut($visit));
        }

        return $visit;
    }

    /**
     * Validate that a visit is in a check-in-able status.
     */
    protected function validateCheckInStatus(VisitorVisit $visit): void
    {
        if (! in_array($visit->status, [VisitStatus::Approved, VisitStatus::PreRegistered])) {
            throw ValidationException::withMessages([
                'status' => 'Only approved or pre-registered visits can be checked in.',
            ]);
        }
    }

    /**
     * Notify the host employee that their visitor has arrived.
     */
    protected function notifyHost(VisitorVisit $visit): void
    {
        $visit->load(['visitor', 'hostEmployee']);

        if ($visit->hostEmployee?->user) {
            $visit->hostEmployee->user->notify(new VisitorArrived($visit));
            $visit->update(['host_notified_at' => now()]);
        }
    }
}
