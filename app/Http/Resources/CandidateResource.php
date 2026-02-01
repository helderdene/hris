<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Candidate
 */
class CandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'linkedin_url' => $this->linkedin_url,
            'portfolio_url' => $this->portfolio_url,
            'resume_file_name' => $this->resume_file_name,
            'skills' => $this->skills,
            'notes' => $this->notes,

            'education' => $this->whenLoaded('education', fn () => $this->education->map(fn ($edu) => [
                'id' => $edu->id,
                'education_level' => $edu->education_level->value,
                'education_level_label' => $edu->education_level->label(),
                'institution' => $edu->institution,
                'field_of_study' => $edu->field_of_study,
                'start_date' => $edu->start_date?->format('Y-m-d'),
                'end_date' => $edu->end_date?->format('Y-m-d'),
                'is_current' => $edu->is_current,
            ])),

            'work_experiences' => $this->whenLoaded('workExperiences', fn () => $this->workExperiences->map(fn ($exp) => [
                'id' => $exp->id,
                'company' => $exp->company,
                'job_title' => $exp->job_title,
                'description' => $exp->description,
                'start_date' => $exp->start_date?->format('Y-m-d'),
                'end_date' => $exp->end_date?->format('Y-m-d'),
                'is_current' => $exp->is_current,
            ])),

            'job_applications' => $this->whenLoaded('jobApplications', fn () => $this->jobApplications->map(fn ($app) => [
                'id' => $app->id,
                'job_posting_id' => $app->job_posting_id,
                'status' => $app->status->value,
                'status_label' => $app->status->label(),
                'status_color' => $app->status->color(),
                'applied_at' => $app->applied_at?->format('Y-m-d H:i:s'),
            ])),

            'applications_count' => $this->whenCounted('jobApplications'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
