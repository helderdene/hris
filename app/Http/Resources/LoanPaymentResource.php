<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\LoanPayment $resource
 */
class LoanPaymentResource extends JsonResource
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
            'employee_loan_id' => $this->resource->employee_loan_id,
            'payroll_deduction_id' => $this->resource->payroll_deduction_id,

            // Payment details
            'amount' => (float) $this->resource->amount,
            'balance_before' => (float) $this->resource->balance_before,
            'balance_after' => (float) $this->resource->balance_after,

            // Formatted amounts
            'amount_formatted' => number_format((float) $this->resource->amount, 2),
            'balance_before_formatted' => number_format((float) $this->resource->balance_before, 2),
            'balance_after_formatted' => number_format((float) $this->resource->balance_after, 2),

            // Payment info
            'payment_date' => $this->resource->payment_date?->toDateString(),
            'payment_source' => $this->resource->payment_source,
            'payment_source_label' => $this->getPaymentSourceLabel(),
            'is_from_payroll' => $this->resource->isFromPayroll(),
            'notes' => $this->resource->notes,

            // Timestamps
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Get a human-readable label for the payment source.
     */
    protected function getPaymentSourceLabel(): string
    {
        return match ($this->resource->payment_source) {
            'payroll' => 'Payroll Deduction',
            'manual' => 'Manual Payment',
            'adjustment' => 'Balance Adjustment',
            default => ucfirst($this->resource->payment_source),
        };
    }
}
