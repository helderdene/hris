<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Certification $resource
 */
class CertificationResource extends JsonResource
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

            // Relationships
            'employee_id' => $this->resource->employee_id,
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => new EmployeeListResource($this->resource->employee)
            ),
            'certification_type_id' => $this->resource->certification_type_id,
            'certification_type' => $this->when(
                $this->resource->relationLoaded('certificationType'),
                fn () => new CertificationTypeResource($this->resource->certificationType)
            ),

            // Details
            'certificate_number' => $this->resource->certificate_number,
            'issuing_body' => $this->resource->issuing_body,
            'issued_date' => $this->resource->issued_date?->format('Y-m-d'),
            'expiry_date' => $this->resource->expiry_date?->format('Y-m-d'),
            'description' => $this->resource->description,

            // Status
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->color(),

            // Computed properties
            'can_be_edited' => $this->resource->can_be_edited,
            'can_be_submitted' => $this->resource->can_be_submitted,
            'is_expiring_soon' => $this->resource->is_expiring_soon,
            'days_until_expiry' => $this->resource->days_until_expiry,
            'expiry_status' => $this->getExpiryStatus(),

            // Workflow timestamps
            'submitted_at' => $this->resource->submitted_at?->toISOString(),
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'rejected_at' => $this->resource->rejected_at?->toISOString(),
            'revoked_at' => $this->resource->revoked_at?->toISOString(),

            // Rejection/Revocation reasons
            'rejection_reason' => $this->resource->rejection_reason,
            'revocation_reason' => $this->resource->revocation_reason,

            // Audit fields
            'approved_by' => $this->resource->approved_by,
            'approved_by_name' => $this->resolveUserName($this->resource->approved_by),
            'rejected_by' => $this->resource->rejected_by,
            'rejected_by_name' => $this->resolveUserName($this->resource->rejected_by),
            'revoked_by' => $this->resource->revoked_by,
            'revoked_by_name' => $this->resolveUserName($this->resource->revoked_by),

            // Files
            'files' => $this->when(
                $this->resource->relationLoaded('files'),
                fn () => CertificationFileResource::collection($this->resource->files)
            ),
            'files_count' => $this->when(
                $this->resource->relationLoaded('files'),
                fn () => $this->resource->files->count()
            ),

            // Timestamps
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the expiry status label.
     */
    protected function getExpiryStatus(): ?string
    {
        if ($this->resource->expiry_date === null) {
            return 'no_expiry';
        }

        $daysUntil = $this->resource->days_until_expiry;

        if ($daysUntil < 0) {
            return 'expired';
        }

        if ($daysUntil <= 30) {
            return 'expiring_soon';
        }

        if ($daysUntil <= 90) {
            return 'expiring';
        }

        return 'valid';
    }

    /**
     * Resolve a user name from user ID.
     */
    protected function resolveUserName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        try {
            $user = app()->environment('testing')
                ? User::find($userId)
                : User::on('mysql')->find($userId);

            return $user?->name ?? 'Unknown User';
        } catch (\Exception $e) {
            return 'Unknown User';
        }
    }
}
