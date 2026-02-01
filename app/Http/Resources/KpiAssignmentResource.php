<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\KpiAssignment $resource
 */
class KpiAssignmentResource extends JsonResource
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
            'kpi_template_id' => $this->resource->kpi_template_id,
            'performance_cycle_participant_id' => $this->resource->performance_cycle_participant_id,
            'target_value' => $this->resource->target_value,
            'weight' => $this->resource->weight,
            'actual_value' => $this->resource->actual_value,
            'achievement_percentage' => $this->resource->achievement_percentage,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'notes' => $this->resource->notes,
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'kpi_template' => $this->when(
                $this->resource->relationLoaded('kpiTemplate'),
                fn () => new KpiTemplateResource($this->resource->kpiTemplate)
            ),
            'participant' => $this->when(
                $this->resource->relationLoaded('performanceCycleParticipant'),
                fn () => $this->formatParticipant()
            ),
            'progress_summary' => $this->when(
                $this->resource->relationLoaded('progressEntries'),
                fn () => $this->formatProgressSummary()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format participant data.
     *
     * @return array<string, mixed>
     */
    protected function formatParticipant(): array
    {
        $participant = $this->resource->performanceCycleParticipant;
        $employee = $participant->employee ?? null;

        return [
            'id' => $participant->id,
            'employee_id' => $participant->employee_id,
            'employee_name' => $employee?->full_name ?? 'Unknown',
            'employee_code' => $employee?->employee_code ?? null,
            'instance_id' => $participant->performance_cycle_instance_id,
        ];
    }

    /**
     * Format progress summary.
     *
     * @return array<string, mixed>
     */
    protected function formatProgressSummary(): array
    {
        $entries = $this->resource->progressEntries;

        return [
            'total_entries' => $entries->count(),
            'latest_entry' => $entries->sortByDesc('recorded_at')->first()?->toArray(),
        ];
    }
}
