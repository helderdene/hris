<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceProgress $resource
 */
class ComplianceProgressResource extends JsonResource
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
            'compliance_assignment_id' => $this->resource->compliance_assignment_id,
            'compliance_module_id' => $this->resource->compliance_module_id,
            'module' => $this->when(
                $this->resource->relationLoaded('complianceModule'),
                fn () => new ComplianceModuleResource($this->resource->complianceModule)
            ),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->color(),
            'started_at' => $this->resource->started_at?->toISOString(),
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'time_spent_minutes' => $this->resource->time_spent_minutes,
            'progress_percentage' => (float) $this->resource->progress_percentage,
            'position_data' => $this->resource->position_data,
            'best_score' => $this->resource->best_score,
            'attempts_made' => $this->resource->attempts_made,
            'remaining_attempts' => $this->resource->getRemainingAttempts(),
            'can_attempt' => $this->resource->canAttempt(),
            'last_accessed_at' => $this->resource->last_accessed_at?->toISOString(),
            'is_completed' => $this->resource->isCompleted(),
            'is_in_progress' => $this->resource->isInProgress(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
