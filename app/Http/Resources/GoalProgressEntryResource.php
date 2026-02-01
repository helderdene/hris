<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\GoalProgressEntry $resource
 */
class GoalProgressEntryResource extends JsonResource
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
            'goal_key_result_id' => $this->resource->goal_key_result_id,
            'progress_value' => $this->resource->progress_value,
            'progress_percentage' => $this->resource->progress_percentage,
            'notes' => $this->resource->notes,
            'recorded_at' => $this->resource->recorded_at?->toISOString(),
            'recorded_by' => $this->resource->recorded_by,
            'recorded_by_user' => $this->when(
                $this->resource->relationLoaded('recordedByUser'),
                fn () => $this->formatRecordedByUser()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Format recorded by user data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatRecordedByUser(): ?array
    {
        $user = $this->resource->recordedByUser;

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
