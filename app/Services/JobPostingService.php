<?php

namespace App\Services;

use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing job posting workflow.
 *
 * Handles publishing, closing, archiving, and creation from requisitions.
 */
class JobPostingService
{
    /**
     * Publish a job posting.
     *
     * @throws ValidationException
     */
    public function publish(JobPosting $posting): JobPosting
    {
        if (! $posting->status->canBePublished()) {
            throw ValidationException::withMessages([
                'status' => 'This job posting cannot be published from its current status.',
            ]);
        }

        return DB::transaction(function () use ($posting) {
            $posting->status = JobPostingStatus::Published;
            $posting->published_at = now();
            $posting->closed_at = null;
            $posting->save();

            return $posting->fresh(['department', 'position', 'createdByEmployee']);
        });
    }

    /**
     * Close a job posting.
     *
     * @throws ValidationException
     */
    public function close(JobPosting $posting): JobPosting
    {
        if ($posting->status !== JobPostingStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Only published job postings can be closed.',
            ]);
        }

        return DB::transaction(function () use ($posting) {
            $posting->status = JobPostingStatus::Closed;
            $posting->closed_at = now();
            $posting->save();

            return $posting->fresh(['department', 'position', 'createdByEmployee']);
        });
    }

    /**
     * Archive a job posting.
     *
     * @throws ValidationException
     */
    public function archive(JobPosting $posting): JobPosting
    {
        if ($posting->status !== JobPostingStatus::Closed) {
            throw ValidationException::withMessages([
                'status' => 'Only closed job postings can be archived.',
            ]);
        }

        return DB::transaction(function () use ($posting) {
            $posting->status = JobPostingStatus::Archived;
            $posting->save();

            return $posting->fresh(['department', 'position', 'createdByEmployee']);
        });
    }

    /**
     * Create a job posting from an approved job requisition.
     *
     * @param  array<string, mixed>  $overrides
     *
     * @throws ValidationException
     */
    public function createFromRequisition(
        JobRequisition $requisition,
        int $createdByEmployeeId,
        array $overrides = []
    ): JobPosting {
        $data = array_merge([
            'job_requisition_id' => $requisition->id,
            'department_id' => $requisition->department_id,
            'position_id' => $requisition->position_id,
            'created_by_employee_id' => $createdByEmployeeId,
            'title' => $requisition->position?->title ?? 'Untitled Position',
            'description' => $requisition->justification ?? '',
            'employment_type' => $requisition->employment_type->value,
            'location' => '',
            'salary_range_min' => $requisition->salary_range_min,
            'salary_range_max' => $requisition->salary_range_max,
            'created_by' => auth()->id(),
        ], $overrides);

        return JobPosting::create($data);
    }
}
