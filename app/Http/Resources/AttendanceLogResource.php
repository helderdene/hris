<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for attendance log list views.
 *
 * @property-read \App\Models\AttendanceLog $resource
 */
class AttendanceLogResource extends JsonResource
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
            'employee_code' => $this->resource->employee_code,
            'employee_name' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => $this->resource->employee->full_name,
                $this->resource->person_name
            ),
            'logged_at' => $this->resource->logged_at?->toISOString(),
            'logged_at_human' => $this->formatLoggedAt(),
            'logged_at_time' => $this->resource->logged_at?->format('g:i A'),
            'logged_at_date' => $this->resource->logged_at?->format('M j, Y'),
            'direction' => $this->resource->direction,
            'confidence' => $this->resource->confidence,
            'confidence_percent' => $this->resource->confidence ? round((float) $this->resource->confidence, 1) : null,
            'device' => $this->when(
                $this->resource->relationLoaded('biometricDevice') && $this->resource->biometricDevice,
                fn () => [
                    'id' => $this->resource->biometricDevice->id,
                    'name' => $this->resource->biometricDevice->name,
                    'device_identifier' => $this->resource->biometricDevice->device_identifier,
                ]
            ),
        ];
    }

    /**
     * Format the logged_at timestamp as human-readable relative time.
     */
    protected function formatLoggedAt(): ?string
    {
        if ($this->resource->logged_at === null) {
            return null;
        }

        return $this->resource->logged_at->diffForHumans();
    }
}
