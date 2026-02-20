<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\VisitorVisit $resource
 */
class VisitorVisitResource extends JsonResource
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
            'visitor' => new VisitorResource($this->whenLoaded('visitor')),
            'work_location' => new WorkLocationResource($this->whenLoaded('workLocation')),
            'host_employee' => $this->whenLoaded('hostEmployee', fn () => [
                'id' => $this->resource->hostEmployee->id,
                'name' => $this->resource->hostEmployee->full_name,
            ]),
            'purpose' => $this->resource->purpose,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'registration_source' => $this->resource->registration_source,
            'expected_at' => $this->resource->expected_at?->toISOString(),
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'host_approved_at' => $this->resource->host_approved_at?->toISOString(),
            'host_approved_by' => $this->resource->host_approved_by,
            'is_admin_approved' => $this->resource->approved_at !== null,
            'is_host_approved' => $this->resource->host_approved_at !== null,
            'rejected_at' => $this->resource->rejected_at?->toISOString(),
            'rejection_reason' => $this->resource->rejection_reason,
            'checked_in_at' => $this->resource->checked_in_at?->toISOString(),
            'checked_out_at' => $this->resource->checked_out_at?->toISOString(),
            'check_in_method' => $this->resource->check_in_method?->value,
            'check_in_method_label' => $this->resource->check_in_method?->label(),
            'qr_token' => $this->resource->qr_token,
            'badge_number' => $this->resource->badge_number,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
