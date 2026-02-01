<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EmployeeAssignmentHistory $resource
 */
class EmployeeAssignmentHistoryResource extends JsonResource
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
            'assignment_type' => [
                'value' => $this->resource->assignment_type->value,
                'label' => $this->resource->assignment_type->label(),
            ],
            'previous_value_id' => $this->resource->previous_value_id,
            'previous_value_name' => $this->resource->previous_value_name,
            'new_value_id' => $this->resource->new_value_id,
            'new_value_name' => $this->resource->new_value_name,
            'effective_date' => $this->resource->effective_date->format('Y-m-d'),
            'remarks' => $this->resource->remarks,
            'changed_by' => $this->resource->changed_by,
            'changed_by_name' => $this->resolveChangedByName(),
            'ended_at' => $this->resource->ended_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Resolve the name of the user who made the change.
     *
     * Since changed_by is a cross-database reference to the platform users table,
     * we need to query it directly.
     */
    protected function resolveChangedByName(): ?string
    {
        if ($this->resource->changed_by === null) {
            return null;
        }

        $user = User::find($this->resource->changed_by);

        return $user?->name;
    }
}
