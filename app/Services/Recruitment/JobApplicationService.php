<?php

namespace App\Services\Recruitment;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing job applications and status transitions.
 */
class JobApplicationService
{
    public function __construct(
        protected ResumeParsingService $resumeParser,
        protected DuplicateDetectionService $duplicateDetector
    ) {}

    /**
     * Create an application from the public careers page.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromPublicApplication(array $data, int $jobPostingId, ?UploadedFile $resume = null): JobApplication
    {
        return DB::transaction(function () use ($data, $jobPostingId, $resume) {
            $candidateData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            if ($resume) {
                $path = $resume->store('resumes', 'local');
                $candidateData['resume_file_path'] = $path;
                $candidateData['resume_file_name'] = $resume->getClientOriginalName();
                $candidateData['resume_parsed_text'] = $this->resumeParser->parseFile($resume);
            }

            // Find existing candidate by email or create new
            $candidate = Candidate::where('email', $data['email'])->first();
            if ($candidate) {
                $candidate->update(array_filter($candidateData, fn ($v) => $v !== null));
            } else {
                $candidate = Candidate::create($candidateData);
            }

            return JobApplication::create([
                'candidate_id' => $candidate->id,
                'job_posting_id' => $jobPostingId,
                'status' => ApplicationStatus::Applied,
                'source' => ApplicationSource::CareersPage,
                'cover_letter' => $data['cover_letter'] ?? null,
            ]);
        });
    }

    /**
     * Create a manual application from the HR panel.
     *
     * @param  array<string, mixed>  $data
     */
    public function createManualApplication(array $data): JobApplication
    {
        return JobApplication::create([
            'candidate_id' => $data['candidate_id'],
            'job_posting_id' => $data['job_posting_id'],
            'status' => ApplicationStatus::Applied,
            'source' => $data['source'] ?? ApplicationSource::ManualEntry,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Transition a job application to a new status.
     *
     * @throws ValidationException
     */
    public function transitionStatus(
        JobApplication $application,
        ApplicationStatus $newStatus,
        ?string $notes = null,
        ?string $rejectionReason = null
    ): JobApplication {
        $currentStatus = $application->status;

        $allowed = $currentStatus->allowedTransitions();
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$currentStatus->label()} to {$newStatus->label()}.",
            ]);
        }

        return DB::transaction(function () use ($application, $currentStatus, $newStatus, $notes, $rejectionReason) {
            // Update the timestamp column for the new status
            $timestampColumn = $newStatus->value.'_at';
            $updateData = [
                'status' => $newStatus,
            ];

            if (in_array($timestampColumn, ['screening_at', 'interview_at', 'assessment_at', 'offer_at', 'hired_at', 'rejected_at', 'withdrawn_at'])) {
                $updateData[$timestampColumn] = now();
            }

            if ($rejectionReason && $newStatus === ApplicationStatus::Rejected) {
                $updateData['rejection_reason'] = $rejectionReason;
            }

            $application->update($updateData);

            JobApplicationStatusHistory::create([
                'job_application_id' => $application->id,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'notes' => $notes,
                'changed_by' => auth()->id(),
            ]);

            return $application->fresh();
        });
    }
}
