<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for development plan lists.
 */
class DevelopmentPlanListResource extends JsonResource
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
            'title' => $this->title,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->colorClass(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'target_completion_date' => $this->target_completion_date?->format('Y-m-d'),
            'progress' => $this->calculateProgress(),
            'is_overdue' => $this->isOverdue(),
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at?->format('Y-m-d'),

            // Relationships
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'position' => $this->employee->position?->title,
            ]),
        ];
    }
}
