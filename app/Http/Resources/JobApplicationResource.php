<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\JobApplication
 */
class JobApplicationResource extends JsonResource
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

            'candidate_id' => $this->candidate_id,
            'candidate' => $this->whenLoaded('candidate', fn () => [
                'id' => $this->candidate->id,
                'full_name' => $this->candidate->full_name,
                'email' => $this->candidate->email,
                'phone' => $this->candidate->phone,
            ]),

            'job_posting_id' => $this->job_posting_id,
            'job_posting' => $this->whenLoaded('jobPosting', fn () => [
                'id' => $this->jobPosting->id,
                'title' => $this->jobPosting->title,
                'slug' => $this->jobPosting->slug,
            ]),

            'assigned_to_employee_id' => $this->assigned_to_employee_id,
            'assigned_to_employee' => $this->whenLoaded('assignedToEmployee', fn () => [
                'id' => $this->assignedToEmployee->id,
                'full_name' => $this->assignedToEmployee->full_name,
            ]),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'cover_letter' => $this->cover_letter,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,

            'allowed_transitions' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $this->status->allowedTransitions()),

            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status?->value,
                'from_status_label' => $h->from_status?->label(),
                'to_status' => $h->to_status->value,
                'to_status_label' => $h->to_status->label(),
                'notes' => $h->notes,
                'created_at' => $h->created_at?->format('Y-m-d H:i:s'),
            ])),

            'assessments' => AssessmentResource::collection($this->whenLoaded('assessments')),
            'background_checks' => BackgroundCheckResource::collection($this->whenLoaded('backgroundChecks')),
            'reference_checks' => ReferenceCheckResource::collection($this->whenLoaded('referenceChecks')),

            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'screening_at' => $this->screening_at?->format('Y-m-d H:i:s'),
            'interview_at' => $this->interview_at?->format('Y-m-d H:i:s'),
            'assessment_at' => $this->assessment_at?->format('Y-m-d H:i:s'),
            'offer_at' => $this->offer_at?->format('Y-m-d H:i:s'),
            'hired_at' => $this->hired_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'withdrawn_at' => $this->withdrawn_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
