<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\LeaveType $resource
 */
class LeaveTypeResource extends JsonResource
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

            // Basic Information
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'description' => $this->resource->description,
            'leave_category' => $this->resource->leave_category?->value,
            'leave_category_label' => $this->resource->leave_category?->label(),

            // Accrual Configuration
            'accrual_method' => $this->resource->accrual_method?->value,
            'accrual_method_label' => $this->resource->accrual_method?->label(),
            'accrual_method_short_label' => $this->resource->accrual_method?->shortLabel(),
            'default_days_per_year' => (float) $this->resource->default_days_per_year,
            'monthly_accrual_rate' => $this->resource->monthly_accrual_rate !== null
                ? (float) $this->resource->monthly_accrual_rate
                : null,
            'tenure_brackets' => $this->resource->tenure_brackets,

            // Carry-over Settings
            'allow_carry_over' => $this->resource->allow_carry_over,
            'max_carry_over_days' => $this->resource->max_carry_over_days !== null
                ? (float) $this->resource->max_carry_over_days
                : null,
            'carry_over_expiry_months' => $this->resource->carry_over_expiry_months,

            // Cash Conversion Settings
            'is_convertible_to_cash' => $this->resource->is_convertible_to_cash,
            'cash_conversion_rate' => $this->resource->cash_conversion_rate !== null
                ? (float) $this->resource->cash_conversion_rate
                : null,
            'max_convertible_days' => $this->resource->max_convertible_days !== null
                ? (float) $this->resource->max_convertible_days
                : null,

            // Eligibility Rules
            'min_tenure_months' => $this->resource->min_tenure_months,
            'eligible_employment_types' => $this->resource->eligible_employment_types,
            'gender_restriction' => $this->resource->gender_restriction?->value,
            'gender_restriction_label' => $this->resource->gender_restriction?->label(),

            // Additional Settings
            'requires_attachment' => $this->resource->requires_attachment,
            'requires_approval' => $this->resource->requires_approval,
            'max_consecutive_days' => $this->resource->max_consecutive_days,
            'min_days_advance_notice' => $this->resource->min_days_advance_notice,

            // Statutory Tracking
            'is_statutory' => $this->resource->is_statutory,
            'statutory_reference' => $this->resource->statutory_reference,
            'is_template' => $this->resource->is_template,

            // Status
            'is_active' => $this->resource->is_active,

            // Formatted fields
            'formatted_days' => $this->getFormattedDays(),
            'formatted_eligibility' => $this->getFormattedEligibility(),

            // Timestamps
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get a formatted string for the number of days.
     */
    protected function getFormattedDays(): string
    {
        $days = (float) $this->resource->default_days_per_year;
        $unit = $days === 1.0 ? 'day' : 'days';

        return "{$days} {$unit}/year";
    }

    /**
     * Get a formatted string for eligibility requirements.
     */
    protected function getFormattedEligibility(): string
    {
        $parts = [];

        if ($this->resource->min_tenure_months !== null && $this->resource->min_tenure_months > 0) {
            $months = $this->resource->min_tenure_months;
            if ($months >= 12) {
                $years = $months / 12;
                $parts[] = $years === 1.0 ? '1 year tenure' : "{$years} years tenure";
            } else {
                $parts[] = "{$months} months tenure";
            }
        }

        if ($this->resource->gender_restriction !== null) {
            $parts[] = $this->resource->gender_restriction->label();
        }

        if ($this->resource->eligible_employment_types !== null && count($this->resource->eligible_employment_types) > 0) {
            $parts[] = implode(', ', array_map('ucfirst', $this->resource->eligible_employment_types));
        }

        return count($parts) > 0 ? implode(' • ', $parts) : 'All employees';
    }
}
