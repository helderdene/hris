<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for attendance log list views.
 *
 * @property-read \App\Models\AttendanceLog $resource
 */
class AttendanceLogResource extends JsonResource
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
            'employee_id' => $this->resource->employee_id,
            'employee_code' => $this->resource->employee_code,
            'employee_name' => $this->when(
                $this->resource->relationLoaded('employee') && $this->resource->employee,
                fn () => $this->resource->employee->full_name,
                $this->resource->person_name
            ),
            'logged_at' => $this->resource->logged_at?->toISOString(),
            'logged_at_human' => $this->formatLoggedAt(),
            'logged_at_time' => $this->resource->logged_at?->format('g:i A'),
            'logged_at_date' => $this->resource->logged_at?->format('M j, Y'),
            'direction' => $this->resource->direction,
            'confidence' => $this->resource->confidence,
            'confidence_percent' => $this->resource->confidence ? round((float) $this->resource->confidence, 1) : null,
            'verification_method' => $this->getVerificationMethod(),
            'source' => $this->resource->source?->value ?? 'biometric',
            'source_label' => $this->resource->source?->label() ?? 'Biometric',
            'device' => $this->when(
                $this->resource->relationLoaded('biometricDevice') && $this->resource->biometricDevice,
                fn () => [
                    'id' => $this->resource->biometricDevice->id,
                    'name' => $this->resource->biometricDevice->name,
                    'device_identifier' => $this->resource->biometricDevice->device_identifier,
                ]
            ),
            'kiosk' => $this->when(
                $this->resource->relationLoaded('kiosk') && $this->resource->kiosk,
                fn () => [
                    'id' => $this->resource->kiosk->id,
                    'name' => $this->resource->kiosk->name,
                ]
            ),
        ];
    }

    /**
     * Determine the verification method from raw payload.
     */
    protected function getVerificationMethod(): string
    {
        $rawPayload = $this->resource->raw_payload;

        if (! $rawPayload || ! isset($rawPayload['info'])) {
            return 'unknown';
        }

        $info = $rawPayload['info'];

        // Check if fingerprint was used
        $fingerUsed = $info['FingerUsed'] ?? null;
        if ($fingerUsed && $fingerUsed !== '0' && $fingerUsed !== '') {
            return 'fingerprint';
        }

        // Check if face recognition was used (confidence > 0)
        $similarity = (float) ($info['similarity1'] ?? 0);
        if ($similarity > 0) {
            return 'face';
        }

        return 'unknown';
    }

    /**
     * Format the logged_at timestamp as human-readable relative time.
     */
    protected function formatLoggedAt(): ?string
    {
        if ($this->resource->logged_at === null) {
            return null;
        }

        return $this->resource->logged_at->diffForHumans();
    }
}
