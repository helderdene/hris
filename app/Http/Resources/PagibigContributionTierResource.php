<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PagibigContributionTier $resource
 */
class PagibigContributionTierResource extends JsonResource
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
            'pagibig_contribution_table_id' => $this->resource->pagibig_contribution_table_id,
            'min_salary' => (float) $this->resource->min_salary,
            'max_salary' => $this->resource->max_salary ? (float) $this->resource->max_salary : null,
            'employee_rate' => (float) $this->resource->employee_rate,
            'employer_rate' => (float) $this->resource->employer_rate,
            'employee_rate_percent' => (float) $this->resource->employee_rate * 100,
            'employer_rate_percent' => (float) $this->resource->employer_rate * 100,
            'salary_range' => $this->resource->salary_range,
            'rates_label' => $this->resource->rates_label,
        ];
    }
}
