<?php

namespace App\Services\Visitor;

use App\Enums\VisitStatus;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Notifications\VisitorApproved;
use App\Notifications\VisitorPreRegistered;
use App\Notifications\VisitorRegistrationRequested;
use App\Notifications\VisitorRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class VisitorRegistrationService
{
    /**
     * Handle visitor-initiated registration from the public page.
     *
     * Creates or reuses a visitor by email, creates a visit with PendingApproval status,
     * and notifies the admin and host employee.
     *
     * @param  array<string, mixed>  $visitorData
     * @param  array<string, mixed>  $visitData
     */
    public function registerFromPublicPage(array $visitorData, array $visitData): VisitorVisit
    {
        // Find or create visitor by email
        $visitor = Visitor::where('email', $visitorData['email'])->first();

        if ($visitor) {
            $visitor->update(array_filter($visitorData));
        } else {
            $visitor = Visitor::create($visitorData);
        }

        $visit = VisitorVisit::create([
            'visitor_id' => $visitor->id,
            'work_location_id' => $visitData['work_location_id'],
            'host_employee_id' => $visitData['host_employee_id'],
            'purpose' => $visitData['purpose'],
            'expected_at' => $visitData['expected_at'] ?? null,
            'status' => VisitStatus::PendingApproval,
            'registration_source' => 'visitor',
            'registration_token' => $this->generateRegistrationToken(),
        ]);

        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        // Notify host employee
        if ($visit->hostEmployee?->user) {
            $visit->hostEmployee->user->notify(new VisitorRegistrationRequested($visit));
        }

        return $visit;
    }

    /**
     * Handle admin-initiated pre-registration (no approval needed).
     *
     * @param  array<string, mixed>  $visitData
     */
    public function preRegister(Visitor $visitor, array $visitData, User $admin): VisitorVisit
    {
        $visit = VisitorVisit::create([
            'visitor_id' => $visitor->id,
            'work_location_id' => $visitData['work_location_id'],
            'host_employee_id' => $visitData['host_employee_id'] ?? null,
            'purpose' => $visitData['purpose'],
            'expected_at' => $visitData['expected_at'] ?? null,
            'status' => VisitStatus::PreRegistered,
            'registration_source' => 'admin',
            'registration_token' => $this->generateRegistrationToken(),
            'qr_token' => $this->generateQrToken(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        // Send confirmation email to visitor
        if ($visitor->email) {
            Notification::route('mail', $visitor->email)
                ->notify(new VisitorPreRegistered($visit));
        }

        // Sync to FR devices if visitor has a photo
        if ($visitor->photo_path) {
            app(VisitorDeviceSyncService::class)
                ->syncVisitorToLocationDevices($visitor, $visit->workLocation);
        }

        return $visit;
    }

    /**
     * Admin approves a pending visitor visit (part of dual approval).
     */
    public function adminApprove(VisitorVisit $visit, User $approver): VisitorVisit
    {
        $visit->update([
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);

        return $this->finalizeApproval($visit);
    }

    /**
     * Host employee approves a pending visitor visit (part of dual approval).
     */
    public function hostApprove(VisitorVisit $visit, User $host): VisitorVisit
    {
        $visit->update([
            'host_approved_at' => now(),
            'host_approved_by' => $host->id,
        ]);

        return $this->finalizeApproval($visit);
    }

    /**
     * Host employee rejects a pending visitor visit.
     */
    public function hostReject(VisitorVisit $visit, User $host, ?string $reason = null): VisitorVisit
    {
        return $this->reject($visit, $host, $reason);
    }

    /**
     * Finalize approval if both admin and host have approved.
     */
    private function finalizeApproval(VisitorVisit $visit): VisitorVisit
    {
        $visit->refresh();

        if (! $visit->isFullyApproved()) {
            return $visit;
        }

        $visit->update([
            'status' => VisitStatus::Approved,
            'qr_token' => $this->generateQrToken(),
        ]);

        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        // Send approval email to visitor with QR code
        if ($visit->visitor->email) {
            Notification::route('mail', $visit->visitor->email)
                ->notify(new VisitorApproved($visit));
        }

        // Sync to FR devices if visitor has a photo
        if ($visit->visitor->photo_path) {
            app(VisitorDeviceSyncService::class)
                ->syncVisitorToLocationDevices($visit->visitor, $visit->workLocation);
        }

        return $visit;
    }

    /**
     * Reject a pending visitor visit.
     */
    public function reject(VisitorVisit $visit, User $rejector, ?string $reason = null): VisitorVisit
    {
        $visit->update([
            'status' => VisitStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $visit->load(['visitor', 'workLocation']);

        // Notify visitor of rejection
        if ($visit->visitor->email) {
            Notification::route('mail', $visit->visitor->email)
                ->notify(new VisitorRejected($visit));
        }

        return $visit;
    }

    /**
     * Generate a cryptographically random QR token.
     */
    public function generateQrToken(): string
    {
        return Str::random(64);
    }

    /**
     * Generate a cryptographically random registration token.
     */
    public function generateRegistrationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Resend confirmation email with QR code.
     */
    public function resendConfirmationEmail(VisitorVisit $visit): void
    {
        $visit->load(['visitor', 'workLocation']);

        if (! $visit->visitor->email || ! $visit->qr_token) {
            return;
        }

        Notification::route('mail', $visit->visitor->email)
            ->notify(new VisitorApproved($visit));
    }
}
