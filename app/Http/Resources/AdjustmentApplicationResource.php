<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for adjustment applications.
 *
 * @mixin \App\Models\AdjustmentApplication
 */
class AdjustmentApplicationResource extends JsonResource
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
            'employee_adjustment_id' => $this->employee_adjustment_id,
            'payroll_period_id' => $this->payroll_period_id,
            'payroll_entry_id' => $this->payroll_entry_id,

            'payroll_period' => $this->whenLoaded('payrollPeriod', fn () => [
                'id' => $this->payrollPeriod->id,
                'name' => $this->payrollPeriod->name,
                'cutoff_start' => $this->payrollPeriod->cutoff_start->format('Y-m-d'),
                'cutoff_end' => $this->payrollPeriod->cutoff_end->format('Y-m-d'),
            ]),

            'amount' => (float) $this->amount,
            'amount_formatted' => number_format((float) $this->amount, 2),

            'balance_before' => $this->balance_before !== null ? (float) $this->balance_before : null,
            'balance_before_formatted' => $this->balance_before !== null
                ? number_format((float) $this->balance_before, 2)
                : null,
            'balance_after' => $this->balance_after !== null ? (float) $this->balance_after : null,
            'balance_after_formatted' => $this->balance_after !== null
                ? number_format((float) $this->balance_after, 2)
                : null,

            'applied_at' => $this->applied_at?->format('Y-m-d H:i:s'),
            'status' => $this->status,

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
