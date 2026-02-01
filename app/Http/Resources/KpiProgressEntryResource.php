<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\KpiProgressEntry $resource
 */
class KpiProgressEntryResource extends JsonResource
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
            'kpi_assignment_id' => $this->resource->kpi_assignment_id,
            'value' => $this->resource->value,
            'notes' => $this->resource->notes,
            'recorded_at' => $this->resource->recorded_at?->toISOString(),
            'recorded_at_formatted' => $this->resource->recorded_at?->format('M j, Y g:i A'),
            'recorded_at_relative' => $this->resource->recorded_at?->diffForHumans(),
            'recorded_by' => $this->resource->recorded_by,
            'recorder' => $this->when(
                $this->resource->relationLoaded('recordedByUser'),
                fn () => $this->formatRecorder()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Format recorder data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatRecorder(): ?array
    {
        $user = $this->resource->recordedByUser;

        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
