<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LoanApplication
 */
class LoanApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,

            // Employee info
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->department?->name,
                'position' => $this->employee->position?->name,
            ]),

            // Loan details
            'loan_type' => $this->loan_type->value,
            'loan_type_label' => $this->loan_type->label(),
            'loan_type_category' => $this->loan_type->category(),
            'amount_requested' => (float) $this->amount_requested,
            'term_months' => $this->term_months,
            'purpose' => $this->purpose,
            'documents' => $this->documents,

            // Status
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),

            // Reviewer info
            'reviewer_employee_id' => $this->reviewer_employee_id,
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
            ]),
            'reviewer_remarks' => $this->reviewer_remarks,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),

            // Linked loan
            'employee_loan_id' => $this->employee_loan_id,
            'employee_loan' => $this->whenLoaded('employeeLoan', fn () => [
                'id' => $this->employeeLoan->id,
                'total_amount' => (float) $this->employeeLoan->total_amount,
                'monthly_deduction' => (float) $this->employeeLoan->monthly_deduction,
                'interest_rate' => (float) $this->employeeLoan->interest_rate,
                'start_date' => $this->employeeLoan->start_date?->format('Y-m-d'),
            ]),

            // Cancellation
            'cancellation_reason' => $this->cancellation_reason,

            // Timestamps
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed flags
            'can_be_edited' => $this->can_be_edited,
            'can_be_cancelled' => $this->can_be_cancelled,
        ];
    }
}
