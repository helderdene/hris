<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevelopmentActivityResource extends JsonResource
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
            'development_plan_item_id' => $this->development_plan_item_id,
            'activity_type' => $this->activity_type?->value,
            'activity_type_label' => $this->activity_type?->label(),
            'activity_type_color' => $this->activity_type?->colorClass(),
            'activity_type_icon' => $this->activity_type?->icon(),
            'title' => $this->title,
            'description' => $this->description,
            'resource_url' => $this->resource_url,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'completion_notes' => $this->completion_notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed fields
            'is_overdue' => $this->isOverdue(),
            'days_until_due' => $this->getDaysUntilDue(),
        ];
    }
}
