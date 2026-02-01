<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\WithholdingTaxTable $resource
 */
class WithholdingTaxTableResource extends JsonResource
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
            'pay_period' => $this->resource->pay_period,
            'pay_period_label' => $this->getPayPeriodLabel(),
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_from_formatted' => $this->resource->effective_from->format('F j, Y'),
            'description' => $this->resource->description,
            'is_active' => $this->resource->is_active,
            'brackets' => $this->when(
                $this->resource->relationLoaded('brackets'),
                fn () => WithholdingTaxBracketResource::collection($this->resource->brackets)
            ),
            'brackets_count' => $this->when(
                $this->resource->relationLoaded('brackets'),
                fn () => $this->resource->brackets->count()
            ),
            'created_by' => $this->resource->created_by,
            'creator_name' => $this->when(
                $this->resource->relationLoaded('creator'),
                fn () => $this->resource->creator?->name
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get a human-readable label for the pay period.
     */
    protected function getPayPeriodLabel(): string
    {
        return match ($this->resource->pay_period) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'semi_monthly' => 'Semi-Monthly',
            'monthly' => 'Monthly',
            default => ucfirst($this->resource->pay_period),
        };
    }
}
