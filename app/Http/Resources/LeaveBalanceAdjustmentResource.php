<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for LeaveBalanceAdjustment model.
 *
 * @property-read \App\Models\LeaveBalanceAdjustment $resource
 */
class LeaveBalanceAdjustmentResource extends JsonResource
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
            'leave_balance_id' => $this->resource->leave_balance_id,
            'adjusted_by' => $this->resource->adjusted_by,

            // Adjustment details
            'adjustment_type' => $this->resource->adjustment_type?->value,
            'adjustment_type_label' => $this->resource->adjustment_type?->label(),
            'is_credit' => $this->resource->isCredit(),
            'days' => (float) $this->resource->days,
            'signed_days' => $this->resource->getSignedDays(),
            'reason' => $this->resource->reason,

            // Audit trail
            'previous_balance' => (float) $this->resource->previous_balance,
            'new_balance' => (float) $this->resource->new_balance,

            // Optional reference
            'reference_type' => $this->resource->reference_type,
            'reference_id' => $this->resource->reference_id,

            // Relationships
            'adjusted_by_user' => $this->whenLoaded('adjustedByUser', function () {
                return [
                    'id' => $this->resource->adjustedByUser->id,
                    'name' => $this->resource->adjustedByUser->name,
                ];
            }),

            // Timestamps
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
