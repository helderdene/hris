<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ReferenceCheck
 */
class ReferenceCheckResource extends JsonResource
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
            'referee_name' => $this->referee_name,
            'referee_email' => $this->referee_email,
            'referee_phone' => $this->referee_phone,
            'referee_company' => $this->referee_company,
            'relationship' => $this->relationship,
            'contacted' => $this->contacted,
            'contacted_at' => $this->contacted_at?->format('Y-m-d H:i:s'),
            'feedback' => $this->feedback,
            'recommendation' => $this->recommendation?->value,
            'recommendation_label' => $this->recommendation?->label(),
            'recommendation_color' => $this->recommendation?->color(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
