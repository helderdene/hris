<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevelopmentPlanCheckInResource extends JsonResource
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
            'development_plan_id' => $this->development_plan_id,
            'check_in_date' => $this->check_in_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // Relationships
            'created_by_user' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),
        ];
    }
}
