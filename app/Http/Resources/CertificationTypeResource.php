<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CertificationType $resource
 */
class CertificationTypeResource extends JsonResource
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
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'validity_period_months' => $this->resource->validity_period_months,
            'validity_period_formatted' => $this->getFormattedValidityPeriod(),
            'reminder_days_before_expiry' => $this->resource->reminder_days_before_expiry,
            'reminder_days_formatted' => $this->getFormattedReminderDays(),
            'is_mandatory' => $this->resource->is_mandatory,
            'is_active' => $this->resource->is_active,
            'certifications_count' => $this->when(
                $this->resource->relationLoaded('certifications'),
                fn () => $this->resource->certifications->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get a formatted string for the validity period.
     */
    protected function getFormattedValidityPeriod(): ?string
    {
        $months = $this->resource->validity_period_months;

        if ($months === null) {
            return 'No expiry';
        }

        if ($months >= 12 && $months % 12 === 0) {
            $years = $months / 12;

            return $years === 1 ? '1 year' : "{$years} years";
        }

        return $months === 1 ? '1 month' : "{$months} months";
    }

    /**
     * Get a formatted string for reminder days.
     */
    protected function getFormattedReminderDays(): ?string
    {
        $days = $this->resource->reminder_days_before_expiry;

        if (empty($days)) {
            return null;
        }

        rsort($days);

        return implode(', ', array_map(fn ($d) => "{$d} days", $days));
    }
}
