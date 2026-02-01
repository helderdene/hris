<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PayrollCycle $resource
 */
class PayrollCycleResource extends JsonResource
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
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'cycle_type' => $this->resource->cycle_type?->value,
            'cycle_type_label' => $this->resource->cycle_type?->label(),
            'cycle_type_description' => $this->resource->cycle_type?->description(),
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'cutoff_rules' => $this->resource->cutoff_rules,
            'is_default' => $this->resource->is_default,
            'is_recurring' => $this->resource->isRecurring(),
            'periods_per_year' => $this->resource->getPeriodsPerYear(),
            'periods_count' => $this->when(
                $this->resource->relationLoaded('payrollPeriods'),
                fn () => $this->resource->payrollPeriods->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
