<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\TrainingWaitlist $resource
 */
class TrainingWaitlistResource extends JsonResource
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
            'training_session_id' => $this->resource->training_session_id,
            'employee_id' => $this->resource->employee_id,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->badgeColor(),
            'position' => $this->resource->position,
            'joined_at' => $this->resource->joined_at?->toISOString(),
            'promoted_at' => $this->resource->promoted_at?->toISOString(),
            'expires_at' => $this->resource->expires_at?->toISOString(),
            'can_cancel' => $this->resource->status->canBeCancelled(),
            'employee' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => [
                    'id' => $this->resource->employee->id,
                    'full_name' => $this->resource->employee->full_name,
                    'employee_number' => $this->resource->employee->employee_number,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->name,
                ]
            ),
            'session' => $this->when(
                $this->resource->relationLoaded('session') && $this->resource->session,
                fn () => new TrainingSessionListResource($this->resource->session)
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
