<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\SssContributionBracket $resource
 */
class SssContributionBracketResource extends JsonResource
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
            'sss_contribution_table_id' => $this->resource->sss_contribution_table_id,
            'min_salary' => (float) $this->resource->min_salary,
            'max_salary' => $this->resource->max_salary ? (float) $this->resource->max_salary : null,
            'monthly_salary_credit' => (float) $this->resource->monthly_salary_credit,
            'employee_contribution' => (float) $this->resource->employee_contribution,
            'employer_contribution' => (float) $this->resource->employer_contribution,
            'total_contribution' => (float) $this->resource->total_contribution,
            'ec_contribution' => (float) $this->resource->ec_contribution,
            'salary_range' => $this->resource->salary_range,
        ];
    }
}
