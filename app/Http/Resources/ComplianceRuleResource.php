<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceAssignmentRule $resource
 */
class ComplianceRuleResource extends JsonResource
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
            'compliance_course_id' => $this->resource->compliance_course_id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'rule_type' => $this->resource->rule_type?->value,
            'rule_type_label' => $this->resource->rule_type?->label(),
            'rule_type_description' => $this->resource->rule_type?->description(),
            'conditions' => $this->resource->conditions,
            'days_to_complete_override' => $this->resource->days_to_complete_override,
            'priority' => $this->resource->priority,
            'is_active' => $this->resource->is_active,
            'apply_to_new_hires' => $this->resource->apply_to_new_hires,
            'apply_to_existing' => $this->resource->apply_to_existing,
            'effective_from' => $this->resource->effective_from?->toDateString(),
            'effective_until' => $this->resource->effective_until?->toDateString(),
            'is_effective' => $this->resource->isEffective(),
            'days_to_complete' => $this->resource->getDaysToComplete(),
            'created_by' => $this->resource->created_by,
            'creator' => $this->when(
                $this->resource->relationLoaded('creator') && $this->resource->creator,
                fn () => [
                    'id' => $this->resource->creator->id,
                    'full_name' => $this->resource->creator->full_name,
                ]
            ),
            'assignments_count' => $this->when(
                $this->resource->relationLoaded('assignments'),
                fn () => $this->resource->assignments->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
