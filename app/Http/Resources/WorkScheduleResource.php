<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\WorkSchedule $resource
 */
class WorkScheduleResource extends JsonResource
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
            'code' => $this->resource->code,
            'schedule_type' => $this->resource->schedule_type?->value,
            'schedule_type_label' => $this->resource->schedule_type?->label(),
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'time_configuration' => $this->resource->time_configuration,
            'time_configuration_summary' => $this->formatTimeConfigurationSummary(),
            'overtime_rules' => $this->resource->overtime_rules,
            'night_differential' => $this->resource->night_differential,
            'assigned_employees_count' => $this->when(
                $this->resource->relationLoaded('employeeScheduleAssignments'),
                fn () => $this->resource->employeeScheduleAssignments->count(),
                fn () => $this->resource->employeeScheduleAssignments()->count()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format a human-readable summary of the time configuration.
     */
    protected function formatTimeConfigurationSummary(): ?string
    {
        $config = $this->resource->time_configuration;
        $type = $this->resource->schedule_type?->value;

        if (! $config || ! $type) {
            return null;
        }

        return match ($type) {
            'fixed' => $this->formatFixedSummary($config),
            'flexible' => $this->formatFlexibleSummary($config),
            'shifting' => $this->formatShiftingSummary($config),
            'compressed' => $this->formatCompressedSummary($config),
            default => null,
        };
    }

    /**
     * Format summary for Fixed schedule type.
     */
    protected function formatFixedSummary(array $config): string
    {
        $startTime = $config['start_time'] ?? '08:00';
        $endTime = $config['end_time'] ?? '17:00';
        $workDays = $config['work_days'] ?? [];

        $daysCount = count($workDays);
        $halfDaySaturday = $config['half_day_saturday'] ?? false;

        $summary = "{$startTime} - {$endTime}";
        if ($halfDaySaturday) {
            $satEndTime = $config['saturday_end_time'] ?? '12:00';
            $summary .= " (Sat until {$satEndTime})";
        }

        return $summary;
    }

    /**
     * Format summary for Flexible schedule type.
     */
    protected function formatFlexibleSummary(array $config): string
    {
        $hoursPerDay = $config['required_hours_per_day'] ?? 8;
        $coreStart = $config['core_hours']['start_time'] ?? '10:00';
        $coreEnd = $config['core_hours']['end_time'] ?? '15:00';

        return "{$hoursPerDay}h/day, Core: {$coreStart} - {$coreEnd}";
    }

    /**
     * Format summary for Shifting schedule type.
     */
    protected function formatShiftingSummary(array $config): string
    {
        $shifts = $config['shifts'] ?? [];
        $shiftCount = count($shifts);

        if ($shiftCount === 0) {
            return 'No shifts defined';
        }

        $shiftNames = array_map(fn ($shift) => $shift['name'] ?? 'Unnamed', $shifts);

        return implode(', ', $shiftNames);
    }

    /**
     * Format summary for Compressed schedule type.
     */
    protected function formatCompressedSummary(array $config): string
    {
        $pattern = $config['pattern'] ?? '4x10';
        $dailyHours = $config['daily_hours'] ?? 10;
        $halfDay = $config['half_day']['enabled'] ?? false;

        $summary = $pattern;
        if ($halfDay) {
            $halfDayInfo = $config['half_day']['day'] ?? '';
            $summary .= " (Half day: {$halfDayInfo})";
        }

        return $summary;
    }
}
