<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewPanelist;
use App\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for interview scheduling and management.
 */
class InterviewService
{
    /**
     * Create an interview for a job application.
     *
     * @param  array{
     *     type: string,
     *     title: string,
     *     scheduled_at: string,
     *     duration_minutes: int,
     *     location?: string|null,
     *     meeting_url?: string|null,
     *     notes?: string|null,
     *     panelist_ids?: int[],
     *     lead_panelist_id?: int|null,
     * }  $data
     */
    public function create(JobApplication $application, array $data): Interview
    {
        return DB::transaction(function () use ($application, $data) {
            $interview = Interview::create([
                'job_application_id' => $application->id,
                'type' => $data['type'],
                'status' => InterviewStatus::Scheduled,
                'title' => $data['title'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'],
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->user()?->employee?->id,
            ]);

            $this->syncPanelists($interview, $data);

            return $interview->load('panelists.employee');
        });
    }

    /**
     * Update an existing interview.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, array $data): Interview
    {
        if ($interview->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot update a '.$interview->status->label().' interview.',
            ]);
        }

        return DB::transaction(function () use ($interview, $data) {
            $interview->update(collect($data)->only([
                'type', 'title', 'scheduled_at', 'duration_minutes',
                'location', 'meeting_url', 'notes',
            ])->toArray());

            if (array_key_exists('panelist_ids', $data)) {
                $this->syncPanelists($interview, $data);
            }

            return $interview->fresh(['panelists.employee']);
        });
    }

    /**
     * Cancel an interview.
     */
    public function cancel(Interview $interview, string $reason): Interview
    {
        if ($interview->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot cancel a '.$interview->status->label().' interview.',
            ]);
        }

        $interview->update([
            'status' => InterviewStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $interview->fresh();
    }

    /**
     * Submit feedback for a panelist.
     */
    public function submitFeedback(InterviewPanelist $panelist, string $feedback, int $rating): InterviewPanelist
    {
        $panelist->update([
            'feedback' => $feedback,
            'rating' => $rating,
            'feedback_submitted_at' => now(),
        ]);

        return $panelist->fresh();
    }

    /**
     * Mark panelists as having received invitation emails.
     *
     * @param  array<int>  $panelistIds
     */
    public function markInvitationsSent(Interview $interview, array $panelistIds = []): void
    {
        $query = $interview->panelists();

        if (! empty($panelistIds)) {
            $query->whereIn('id', $panelistIds);
        }

        $query->update(['invitation_sent_at' => now()]);
    }

    /**
     * Sync panelists for an interview.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncPanelists(Interview $interview, array $data): void
    {
        $panelistIds = $data['panelist_ids'] ?? [];
        $leadId = $data['lead_panelist_id'] ?? null;

        if (empty($panelistIds)) {
            return;
        }

        // Remove existing panelists not in the new list
        $interview->panelists()->whereNotIn('employee_id', $panelistIds)->delete();

        foreach ($panelistIds as $employeeId) {
            InterviewPanelist::updateOrCreate(
                [
                    'interview_id' => $interview->id,
                    'employee_id' => $employeeId,
                ],
                [
                    'is_lead' => $leadId !== null && $employeeId === $leadId,
                ]
            );
        }
    }
}
