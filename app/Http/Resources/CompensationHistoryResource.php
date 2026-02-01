<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CompensationHistory $resource
 */
class CompensationHistoryResource extends JsonResource
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
            'previous_basic_pay' => $this->formatDecimal($this->resource->previous_basic_pay),
            'new_basic_pay' => $this->formatDecimal($this->resource->new_basic_pay),
            'previous_pay_type' => $this->resource->previous_pay_type?->value,
            'previous_pay_type_label' => $this->resource->previous_pay_type?->label(),
            'new_pay_type' => $this->resource->new_pay_type?->value,
            'new_pay_type_label' => $this->resource->new_pay_type?->label(),
            'effective_date' => $this->resource->effective_date?->format('Y-m-d'),
            'remarks' => $this->resource->remarks,
            'changed_by' => $this->resource->changed_by,
            'changed_by_name' => $this->resolveChangedByName(),
            'ended_at' => $this->resource->ended_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format a decimal value to 2 decimal places.
     */
    protected function formatDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
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
