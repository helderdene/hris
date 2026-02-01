<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight resource for payroll entry list views.
 *
 * @property-read \App\Models\PayrollEntry $resource
 */
class PayrollEntryListResource extends JsonResource
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
            'payroll_period_id' => $this->resource->payroll_period_id,
            'employee_id' => $this->resource->employee_id,
            'employee_number' => $this->resource->employee_number,
            'employee_name' => $this->resource->employee_name,
            'department_name' => $this->resource->department_name,
            'position_name' => $this->resource->position_name,
            'days_worked' => $this->resource->days_worked,
            'gross_pay' => $this->resource->gross_pay,
            'total_deductions' => $this->resource->total_deductions,
            'net_pay' => $this->resource->net_pay,
            'formatted' => [
                'gross_pay' => number_format((float) $this->resource->gross_pay, 2),
                'total_deductions' => number_format((float) $this->resource->total_deductions, 2),
                'net_pay' => number_format((float) $this->resource->net_pay, 2),
            ],
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'computed_at' => $this->resource->computed_at?->toISOString(),
        ];
    }
}
