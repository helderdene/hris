<?php

namespace App\Services\Recruitment;

use App\Models\Assessment;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\JobApplication;
use App\Models\ReferenceCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Business logic for assessments, background checks, and reference checks.
 */
class AssessmentService
{
    /**
     * Create an assessment for a job application.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAssessment(JobApplication $application, array $data): Assessment
    {
        return Assessment::create([
            'job_application_id' => $application->id,
            'created_by' => auth()->user()?->employee?->id,
            ...$data,
        ]);
    }

    /**
     * Update an existing assessment.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAssessment(Assessment $assessment, array $data): Assessment
    {
        $assessment->update($data);

        return $assessment->fresh();
    }

    /**
     * Delete an assessment.
     */
    public function deleteAssessment(Assessment $assessment): void
    {
        $assessment->delete();
    }

    /**
     * Create a background check for a job application.
     *
     * @param  array<string, mixed>  $data
     */
    public function createBackgroundCheck(JobApplication $application, array $data): BackgroundCheck
    {
        return BackgroundCheck::create([
            'job_application_id' => $application->id,
            'created_by' => auth()->user()?->employee?->id,
            ...$data,
        ]);
    }

    /**
     * Update an existing background check.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBackgroundCheck(BackgroundCheck $backgroundCheck, array $data): BackgroundCheck
    {
        $backgroundCheck->update($data);

        return $backgroundCheck->fresh();
    }

    /**
     * Delete a background check and its documents.
     */
    public function deleteBackgroundCheck(BackgroundCheck $backgroundCheck): void
    {
        foreach ($backgroundCheck->documents as $document) {
            Storage::disk('local')->delete($document->file_path);
        }

        $backgroundCheck->delete();
    }

    /**
     * Upload a document for a background check.
     */
    public function uploadDocument(BackgroundCheck $backgroundCheck, UploadedFile $file): BackgroundCheckDocument
    {
        $path = $file->store('background-checks', 'local');

        return BackgroundCheckDocument::create([
            'background_check_id' => $backgroundCheck->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->user()?->employee?->id,
        ]);
    }

    /**
     * Delete a background check document.
     */
    public function deleteDocument(BackgroundCheckDocument $document): void
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    /**
     * Create a reference check for a job application.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReferenceCheck(JobApplication $application, array $data): ReferenceCheck
    {
        return ReferenceCheck::create([
            'job_application_id' => $application->id,
            'contacted' => false,
            'created_by' => auth()->user()?->employee?->id,
            ...$data,
        ]);
    }

    /**
     * Update an existing reference check.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReferenceCheck(ReferenceCheck $referenceCheck, array $data): ReferenceCheck
    {
        $referenceCheck->update($data);

        return $referenceCheck->fresh();
    }

    /**
     * Delete a reference check.
     */
    public function deleteReferenceCheck(ReferenceCheck $referenceCheck): void
    {
        $referenceCheck->delete();
    }

    /**
     * Mark a reference check as contacted.
     */
    public function markContacted(ReferenceCheck $referenceCheck): ReferenceCheck
    {
        $referenceCheck->update([
            'contacted' => true,
            'contacted_at' => now(),
        ]);

        return $referenceCheck->fresh();
    }
}
