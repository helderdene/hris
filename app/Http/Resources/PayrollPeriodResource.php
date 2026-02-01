<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PayrollPeriod $resource
 */
class PayrollPeriodResource extends JsonResource
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
            'payroll_cycle_id' => $this->resource->payroll_cycle_id,
            'payroll_cycle' => $this->when(
                $this->resource->relationLoaded('payrollCycle'),
                fn () => $this->resource->payrollCycle
                    ? new PayrollCycleResource($this->resource->payrollCycle)
                    : null
            ),
            'name' => $this->resource->name,
            'period_type' => $this->resource->period_type?->value,
            'period_type_label' => $this->resource->period_type?->label(),
            'year' => $this->resource->year,
            'period_number' => $this->resource->period_number,
            'cutoff_start' => $this->resource->cutoff_start?->toDateString(),
            'cutoff_end' => $this->resource->cutoff_end?->toDateString(),
            'date_range' => $this->resource->getDateRange(),
            'pay_date' => $this->resource->pay_date?->toDateString(),
            'formatted_pay_date' => $this->resource->pay_date?->format('F j, Y'),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'is_editable' => $this->resource->isEditable(),
            'is_deletable' => $this->resource->isDeletable(),
            'allowed_transitions' => array_map(
                fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                $this->resource->status?->allowedTransitions() ?? []
            ),
            'employee_count' => $this->resource->employee_count,
            'total_gross' => $this->resource->total_gross,
            'total_deductions' => $this->resource->total_deductions,
            'total_net' => $this->resource->total_net,
            'formatted_totals' => [
                'gross' => number_format((float) $this->resource->total_gross, 2),
                'deductions' => number_format((float) $this->resource->total_deductions, 2),
                'net' => number_format((float) $this->resource->total_net, 2),
            ],
            'opened_at' => $this->resource->opened_at?->toISOString(),
            'closed_at' => $this->resource->closed_at?->toISOString(),
            'closed_by' => $this->resource->closed_by,
            'closed_by_user' => $this->when(
                $this->resource->relationLoaded('closedByUser'),
                fn () => $this->resource->closedByUser
                    ? [
                        'id' => $this->resource->closedByUser->id,
                        'name' => $this->resource->closedByUser->name,
                    ]
                    : null
            ),
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
