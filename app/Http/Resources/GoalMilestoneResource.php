<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\GoalMilestone $resource
 */
class GoalMilestoneResource extends JsonResource
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
            'goal_id' => $this->resource->goal_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'due_date' => $this->resource->due_date?->format('Y-m-d'),
            'is_completed' => $this->resource->is_completed,
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'completed_by' => $this->resource->completed_by,
            'sort_order' => $this->resource->sort_order,
            'is_overdue' => $this->resource->isOverdue(),
            'completed_by_user' => $this->when(
                $this->resource->relationLoaded('completedByUser'),
                fn () => $this->formatCompletedByUser()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format completed by user data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatCompletedByUser(): ?array
    {
        $user = $this->resource->completedByUser;

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
