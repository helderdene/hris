<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for EmployeeDeviceSync model.
 *
 * @mixin \App\Models\EmployeeDeviceSync
 */
class EmployeeDeviceSyncResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'device_id' => $this->biometric_device_id,
            'device_name' => $this->whenLoaded('biometricDevice', fn () => $this->biometricDevice->name),
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->full_name),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'last_attempted_at' => $this->last_attempted_at?->toISOString(),
            'retry_count' => $this->retry_count,
            'has_error' => $this->hasError(),
            'last_error' => $this->when($this->hasError(), $this->last_error),
        ];
    }
}
