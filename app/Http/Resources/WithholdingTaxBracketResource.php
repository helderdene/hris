<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\WithholdingTaxBracket $resource
 */
class WithholdingTaxBracketResource extends JsonResource
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
            'withholding_tax_table_id' => $this->resource->withholding_tax_table_id,
            'min_compensation' => (float) $this->resource->min_compensation,
            'max_compensation' => $this->resource->max_compensation ? (float) $this->resource->max_compensation : null,
            'base_tax' => (float) $this->resource->base_tax,
            'excess_rate' => (float) $this->resource->excess_rate,
            'excess_rate_percentage' => (float) $this->resource->excess_rate * 100,
            'compensation_range' => $this->resource->compensation_range,
        ];
    }
}
