<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\EmployeeLoan $resource
 */
class EmployeeLoanResource extends JsonResource
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

            // Employee info
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => [
                    'id' => $this->resource->employee->id,
                    'employee_number' => $this->resource->employee->employee_number,
                    'full_name' => $this->resource->employee->full_name,
                    'department' => $this->resource->employee->department?->name,
                    'position' => $this->resource->employee->position?->name,
                ]
            ),

            // Loan identification
            'loan_type' => $this->resource->loan_type->value,
            'loan_type_label' => $this->resource->loan_type->label(),
            'loan_type_category' => $this->resource->loan_type->category(),
            'is_government_loan' => $this->resource->loan_type->isGovernmentLoan(),
            'loan_code' => $this->resource->loan_code,
            'reference_number' => $this->resource->reference_number,

            // Amounts
            'principal_amount' => (float) $this->resource->principal_amount,
            'interest_rate' => (float) $this->resource->interest_rate,
            'monthly_deduction' => (float) $this->resource->monthly_deduction,
            'term_months' => $this->resource->term_months,
            'total_amount' => (float) $this->resource->total_amount,
            'total_paid' => (float) $this->resource->total_paid,
            'remaining_balance' => (float) $this->resource->remaining_balance,

            // Formatted amounts
            'principal_amount_formatted' => number_format((float) $this->resource->principal_amount, 2),
            'monthly_deduction_formatted' => number_format((float) $this->resource->monthly_deduction, 2),
            'total_amount_formatted' => number_format((float) $this->resource->total_amount, 2),
            'total_paid_formatted' => number_format((float) $this->resource->total_paid, 2),
            'remaining_balance_formatted' => number_format((float) $this->resource->remaining_balance, 2),

            // Progress
            'progress_percentage' => round($this->resource->getProgressPercentage(), 1),
            'deduction_amount' => $this->resource->getDeductionAmount(),

            // Dates
            'start_date' => $this->resource->start_date?->toDateString(),
            'expected_end_date' => $this->resource->expected_end_date?->toDateString(),
            'actual_end_date' => $this->resource->actual_end_date?->toDateString(),

            // Status
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'status_color' => $this->resource->status->color(),
            'is_deductible' => $this->resource->status->isDeductible(),
            'allowed_transitions' => array_map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ], $this->resource->status->allowedTransitions()),

            // Additional info
            'notes' => $this->resource->notes,
            'metadata' => $this->resource->metadata,

            // Payments
            'payments' => $this->when(
                $this->resource->relationLoaded('payments'),
                fn () => LoanPaymentResource::collection($this->resource->payments)
            ),
            'payments_count' => $this->when(
                isset($this->resource->payments_count),
                fn () => $this->resource->payments_count
            ),

            // Audit
            'created_by' => $this->when(
                $this->resource->relationLoaded('createdBy'),
                fn () => $this->resource->createdBy?->name
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
