<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim resource for leave calendar entries.
 *
 * Returns only the data needed for calendar display to minimize payload size.
 */
class LeaveCalendarEntryResource extends JsonResource
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
            'employee' => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'initials' => $this->getEmployeeInitials(),
                'department_id' => $this->employee->department_id,
                'department' => $this->employee->department?->name,
            ],
            'leave_type' => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'category' => $this->leaveType->leave_category->value,
            ],
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'total_days' => (float) $this->total_days,
            'is_half_day_start' => $this->is_half_day_start,
            'is_half_day_end' => $this->is_half_day_end,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'reference_number' => $this->reference_number,
        ];
    }

    /**
     * Get initials from employee's full name.
     */
    protected function getEmployeeInitials(): string
    {
        $name = $this->employee->full_name;
        $parts = preg_split('/\s+/', trim($name));

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        $first = $parts[0][0] ?? '';
        $last = $parts[count($parts) - 1][0] ?? '';

        return strtoupper($first.$last);
    }
}
