<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ComplianceCertificate $resource
 */
class ComplianceCertificateResource extends JsonResource
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
            'compliance_assignment_id' => $this->resource->compliance_assignment_id,
            'certificate_number' => $this->resource->certificate_number,
            'issued_date' => $this->resource->issued_date?->toDateString(),
            'valid_until' => $this->resource->valid_until?->toDateString(),
            'final_score' => $this->resource->final_score,
            'file_name' => $this->resource->file_name,
            'has_file' => $this->resource->file_path !== null,
            'metadata' => $this->resource->metadata,
            'is_revoked' => $this->resource->is_revoked,
            'revocation_reason' => $this->resource->revocation_reason,
            'revoked_at' => $this->resource->revoked_at?->toISOString(),
            'revoked_by' => $this->when(
                $this->resource->relationLoaded('revokedByEmployee') && $this->resource->revokedByEmployee,
                fn () => [
                    'id' => $this->resource->revokedByEmployee->id,
                    'full_name' => $this->resource->revokedByEmployee->full_name,
                ]
            ),
            'is_valid' => $this->resource->isValid(),
            'is_expired' => $this->resource->isExpired(),
            'is_expiring_soon' => $this->resource->isExpiringSoon(),
            'days_until_expiration' => $this->resource->getDaysUntilExpiration(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
