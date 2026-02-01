<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for goal listings.
 *
 * @property-read \App\Models\Goal $resource
 */
class GoalListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'employee_id' => $this->resource->employee_id,
            'goal_type' => $this->resource->goal_type?->value,
            'goal_type_label' => $this->resource->goal_type?->label(),
            'goal_type_color' => $this->resource->goal_type?->colorClass(),
            'title' => $this->resource->title,
            'category' => $this->resource->category,
            'visibility' => $this->resource->visibility?->value,
            'visibility_label' => $this->resource->visibility?->label(),
            'priority' => $this->resource->priority?->value,
            'priority_label' => $this->resource->priority?->label(),
            'priority_color' => $this->resource->priority?->colorClass(),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'approval_status' => $this->resource->approval_status?->value,
            'approval_status_label' => $this->resource->approval_status?->label(),
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'due_date' => $this->resource->due_date?->format('Y-m-d'),
            'progress_percentage' => $this->resource->progress_percentage,
            'is_overdue' => $this->resource->isOverdue(),
            'days_remaining' => $this->resource->getDaysRemaining(),
            'employee_name' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => $this->resource->employee?->full_name
            ),
            'department_name' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee?->relationLoaded('department'),
                fn () => $this->resource->employee?->department?->name
            ),
            'key_results_count' => $this->when(
                $this->resource->relationLoaded('keyResults'),
                fn () => $this->resource->keyResults->count()
            ),
            'milestones_count' => $this->when(
                $this->resource->relationLoaded('milestones'),
                fn () => $this->resource->milestones->count()
            ),
            'milestones_completed' => $this->when(
                $this->resource->relationLoaded('milestones'),
                fn () => $this->resource->milestones->where('is_completed', true)->count()
            ),
        ];
    }
}
