<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\JobApplication
 */
class JobApplicationListResource extends JsonResource
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
            ]),
            'job_posting_id' => $this->job_posting_id,
            'job_posting' => $this->whenLoaded('jobPosting', fn () => [
                'id' => $this->jobPosting->id,
                'title' => $this->jobPosting->title,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'allowed_transitions' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $this->status->allowedTransitions()),
        ];
    }
}
