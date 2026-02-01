<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Offer
 */
class OfferResource extends JsonResource
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

            'job_application_id' => $this->job_application_id,
            'job_application' => $this->whenLoaded('jobApplication', fn () => [
                'id' => $this->jobApplication->id,
                'candidate' => $this->whenLoaded('jobApplication.candidate', fn () => [
                    'id' => $this->jobApplication->candidate->id,
                    'full_name' => $this->jobApplication->candidate->full_name,
                    'email' => $this->jobApplication->candidate->email,
                ]),
                'job_posting' => $this->whenLoaded('jobApplication.jobPosting', fn () => [
                    'id' => $this->jobApplication->jobPosting->id,
                    'title' => $this->jobApplication->jobPosting->title,
                ]),
            ]),

            'offer_template_id' => $this->offer_template_id,
            'offer_template' => $this->whenLoaded('offerTemplate', fn () => [
                'id' => $this->offerTemplate->id,
                'name' => $this->offerTemplate->name,
            ]),

            'content' => $this->content,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'allowed_transitions' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $this->status->allowedTransitions()),

            'salary' => $this->salary,
            'salary_currency' => $this->salary_currency,
            'salary_frequency' => $this->salary_frequency,
            'benefits' => $this->benefits,
            'terms' => $this->terms,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'position_title' => $this->position_title,
            'department' => $this->department,
            'work_location' => $this->work_location,
            'employment_type' => $this->employment_type,
            'pdf_path' => $this->pdf_path,

            'created_by' => $this->created_by,
            'revoked_by' => $this->revoked_by,
            'decline_reason' => $this->decline_reason,
            'revoke_reason' => $this->revoke_reason,

            'signatures' => OfferSignatureResource::collection($this->whenLoaded('signatures')),

            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            'viewed_at' => $this->viewed_at?->format('Y-m-d H:i:s'),
            'accepted_at' => $this->accepted_at?->format('Y-m-d H:i:s'),
            'declined_at' => $this->declined_at?->format('Y-m-d H:i:s'),
            'expired_at' => $this->expired_at?->format('Y-m-d H:i:s'),
            'revoked_at' => $this->revoked_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
