<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PayrollDeduction $resource
 */
class PayrollDeductionResource extends JsonResource
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
            'deduction_type' => $this->resource->deduction_type?->value,
            'deduction_type_label' => $this->resource->deduction_type?->label(),
            'deduction_code' => $this->resource->deduction_code,
            'description' => $this->resource->description,
            'basis_amount' => $this->resource->basis_amount,
            'rate' => $this->resource->rate,
            'rate_percentage' => round((float) $this->resource->rate * 100, 2),
            'amount' => $this->resource->amount,
            'formatted_amount' => number_format((float) $this->resource->amount, 2),
            'is_employee_share' => $this->resource->is_employee_share,
            'is_employer_share' => $this->resource->is_employer_share,
            'share_type' => $this->resource->share_type_label,
            'computation_breakdown' => $this->resource->computation_breakdown,
            'contribution_table_type' => $this->resource->contribution_table_type,
            'contribution_table_id' => $this->resource->contribution_table_id,
            'remarks' => $this->resource->remarks,
        ];
    }
}
