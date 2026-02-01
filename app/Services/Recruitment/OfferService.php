<?php

namespace App\Services\Recruitment;

use App\Actions\CreateNewHireUserAction;
use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\OfferSignature;
use App\Models\OfferTemplate;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use App\Notifications\OfferSent;
use App\Notifications\OfferViewed;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing the offer lifecycle.
 */
class OfferService
{
    /**
     * Create a new offer for a job application.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOffer(array $data): Offer
    {
        return DB::transaction(function () use ($data) {
            $content = $data['content'] ?? '';

            if (! empty($data['offer_template_id'])) {
                $template = OfferTemplate::findOrFail($data['offer_template_id']);
                $application = \App\Models\JobApplication::with('candidate')->find($data['job_application_id']);
                $placeholderData = array_merge($data, [
                    'candidate_name' => $application?->candidate?->full_name ?? '',
                ]);
                $content = $this->resolvePlaceholders($template->content, $placeholderData);
            }

            return Offer::create(array_merge($data, [
                'content' => $content,
                'status' => OfferStatus::Draft,
                'created_by' => auth()->id(),
            ]));
        });
    }

    /**
     * Send an offer to the candidate.
     *
     * @throws ValidationException
     */
    public function sendOffer(Offer $offer): Offer
    {
        $this->validateTransition($offer, OfferStatus::Sent);

        return DB::transaction(function () use ($offer) {
            $offer->update([
                'status' => OfferStatus::Sent,
                'sent_at' => now(),
            ]);

            $candidate = $offer->jobApplication->candidate;
            Notification::route('mail', $candidate->email)
                ->notify(new OfferSent($offer));

            return $offer->fresh();
        });
    }

    /**
     * Record that a candidate has viewed the offer.
     */
    public function recordView(Offer $offer): Offer
    {
        if ($offer->status !== OfferStatus::Sent) {
            return $offer;
        }

        $offer->update([
            'status' => OfferStatus::Viewed,
            'viewed_at' => now(),
        ]);

        if ($offer->creator) {
            $offer->creator->notify(new OfferViewed($offer));
        }

        return $offer->fresh();
    }

    /**
     * Accept an offer with a candidate signature.
     *
     * @param  array<string, mixed>  $signatureData
     *
     * @throws ValidationException
     */
    public function acceptOffer(Offer $offer, array $signatureData): Offer
    {
        $this->validateTransition($offer, OfferStatus::Accepted);

        return DB::transaction(function () use ($offer, $signatureData) {
            $offer->update([
                'status' => OfferStatus::Accepted,
                'accepted_at' => now(),
            ]);

            OfferSignature::create([
                'offer_id' => $offer->id,
                'signer_type' => 'candidate',
                'signer_name' => $signatureData['signer_name'],
                'signer_email' => $signatureData['signer_email'],
                'signature_data' => $signatureData['signature_data'],
                'ip_address' => $signatureData['ip_address'] ?? null,
                'user_agent' => $signatureData['user_agent'] ?? null,
                'signed_at' => now(),
            ]);

            // Transition the job application to Hired
            $application = $offer->jobApplication;
            if ($application->status === ApplicationStatus::Offer) {
                app(JobApplicationService::class)->transitionStatus(
                    $application,
                    ApplicationStatus::Hired,
                    'Offer accepted by candidate',
                );
            }

            // Generate PDF
            $this->generatePdf($offer->fresh());

            // Notify HR
            $this->notifyStakeholders($offer, OfferAccepted::class);

            // Auto-create preboarding checklist
            app(\App\Services\PreboardingService::class)->createFromTemplate($offer);

            // Auto-create user account for the new hire (without sending email)
            $this->createNewHireUser($offer);

            return $offer->fresh();
        });
    }

    /**
     * Decline an offer.
     *
     * @throws ValidationException
     */
    public function declineOffer(Offer $offer, ?string $reason = null): Offer
    {
        $this->validateTransition($offer, OfferStatus::Declined);

        return DB::transaction(function () use ($offer, $reason) {
            $offer->update([
                'status' => OfferStatus::Declined,
                'declined_at' => now(),
                'decline_reason' => $reason,
            ]);

            $this->notifyStakeholders($offer, OfferDeclined::class);

            return $offer->fresh();
        });
    }

    /**
     * Revoke an offer.
     *
     * @throws ValidationException
     */
    public function revokeOffer(Offer $offer, ?string $reason = null): Offer
    {
        $this->validateTransition($offer, OfferStatus::Revoked);

        $offer->update([
            'status' => OfferStatus::Revoked,
            'revoked_at' => now(),
            'revoked_by' => auth()->id(),
            'revoke_reason' => $reason,
        ]);

        return $offer->fresh();
    }

    /**
     * Replace placeholders in template content with actual values.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolvePlaceholders(string $content, array $data): string
    {
        $replacements = [
            '{{candidate_name}}' => $data['candidate_name'] ?? '',
            '{{position}}' => $data['position_title'] ?? '',
            '{{salary}}' => number_format((float) ($data['salary'] ?? 0), 2).' '.($data['salary_currency'] ?? 'PHP'),
            '{{start_date}}' => $data['start_date'] ?? '',
            '{{benefits}}' => is_array($data['benefits'] ?? null) ? implode(', ', $data['benefits']) : ($data['benefits'] ?? ''),
            '{{department}}' => $data['department'] ?? '',
            '{{work_location}}' => $data['work_location'] ?? '',
            '{{employment_type}}' => $data['employment_type'] ?? '',
            '{{salary_frequency}}' => $data['salary_frequency'] ?? '',
            '{{company_name}}' => $data['company_name'] ?? tenant()?->name ?? config('app.name'),
            '{{expiry_date}}' => $data['expiry_date'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Generate a PDF for the offer letter.
     */
    public function generatePdf(Offer $offer): string
    {
        $offer->load(['jobApplication.candidate', 'signatures']);

        $tenant = tenant();
        $tenantId = $tenant ? $tenant->id : 'default';

        $data = [
            'offer' => $offer,
            'candidate' => $offer->jobApplication->candidate,
            'signatures' => $offer->signatures,
        ];

        $pdf = Pdf::loadView('pdf.offer-letter', $data);
        $pdf->setPaper('A4', 'portrait');

        $directory = "offer-letters/{$tenantId}";
        $filename = "{$directory}/{$offer->id}.pdf";

        Storage::disk('local')->makeDirectory($directory);
        Storage::disk('local')->put($filename, $pdf->output());

        $offer->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Validate that a status transition is allowed.
     *
     * @throws ValidationException
     */
    protected function validateTransition(Offer $offer, OfferStatus $newStatus): void
    {
        $allowed = $offer->status->allowedTransitions();

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$offer->status->label()} to {$newStatus->label()}.",
            ]);
        }
    }

    /**
     * Notify HR stakeholders about an offer event.
     *
     * @param  class-string  $notificationClass
     */
    protected function notifyStakeholders(Offer $offer, string $notificationClass): void
    {
        if ($offer->creator) {
            $offer->creator->notify(new $notificationClass($offer));
        }
    }

    /**
     * Create a user account for the newly hired candidate.
     *
     * The user is created with an invitation token but no email is sent.
     * HR must manually trigger the account setup email.
     */
    protected function createNewHireUser(Offer $offer): void
    {
        $candidate = $offer->jobApplication->candidate;
        $tenant = tenant();

        if (! $tenant || ! $candidate->email) {
            return;
        }

        $action = new CreateNewHireUserAction;
        $action->execute(
            email: $candidate->email,
            name: $candidate->full_name,
            tenant: $tenant
        );
    }
}
