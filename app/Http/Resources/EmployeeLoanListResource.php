<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for loan listings.
 *
 * @property-read \App\Models\EmployeeLoan $resource
 */
class EmployeeLoanListResource extends JsonResource
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

            // Employee info (minimal)
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => [
                    'id' => $this->resource->employee->id,
                    'employee_number' => $this->resource->employee->employee_number,
                    'full_name' => $this->resource->employee->full_name,
                ]
            ),

            // Loan identification
            'loan_type' => $this->resource->loan_type->value,
            'loan_type_label' => $this->resource->loan_type->label(),
            'loan_type_category' => $this->resource->loan_type->category(),
            'loan_code' => $this->resource->loan_code,
            'reference_number' => $this->resource->reference_number,

            // Key amounts
            'monthly_deduction' => (float) $this->resource->monthly_deduction,
            'remaining_balance' => (float) $this->resource->remaining_balance,
            'monthly_deduction_formatted' => number_format((float) $this->resource->monthly_deduction, 2),
            'remaining_balance_formatted' => number_format((float) $this->resource->remaining_balance, 2),

            // Progress
            'progress_percentage' => round($this->resource->getProgressPercentage(), 1),

            // Status
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->color(),

            // Dates
            'start_date' => $this->resource->start_date?->toDateString(),
            'expected_end_date' => $this->resource->expected_end_date?->toDateString(),
        ];
    }
}
