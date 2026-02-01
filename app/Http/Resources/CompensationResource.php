<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EmployeeCompensation $resource
 */
class CompensationResource extends JsonResource
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
            'basic_pay' => number_format((float) $this->resource->basic_pay, 2, '.', ''),
            'currency' => $this->resource->currency,
            'pay_type' => $this->resource->pay_type?->value,
            'pay_type_label' => $this->resource->pay_type?->label(),
            'effective_date' => $this->resource->effective_date?->format('Y-m-d'),
            'bank_name' => $this->resource->bank_name,
            'account_name' => $this->resource->account_name,
            'account_number' => $this->resource->account_number,
            'account_type' => $this->resource->account_type?->value,
            'account_type_label' => $this->resource->account_type?->label(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
