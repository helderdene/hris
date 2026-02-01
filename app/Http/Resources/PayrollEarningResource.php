<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PayrollEarning $resource
 */
class PayrollEarningResource extends JsonResource
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
            'earning_type' => $this->resource->earning_type?->value,
            'earning_type_label' => $this->resource->earning_type?->label(),
            'earning_code' => $this->resource->earning_code,
            'description' => $this->resource->description,
            'quantity' => $this->resource->quantity,
            'quantity_unit' => $this->resource->quantity_unit,
            'formatted_quantity' => $this->resource->formatted_quantity,
            'rate' => $this->resource->rate,
            'multiplier' => $this->resource->multiplier,
            'amount' => $this->resource->amount,
            'formatted_amount' => number_format((float) $this->resource->amount, 2),
            'is_taxable' => $this->resource->is_taxable,
            'computation_breakdown' => $this->resource->computation_breakdown,
            'remarks' => $this->resource->remarks,
        ];
    }
}
