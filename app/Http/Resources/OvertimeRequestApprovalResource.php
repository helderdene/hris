<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OvertimeRequestApproval
 */
class OvertimeRequestApprovalResource extends JsonResource
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
            'overtime_request_id' => $this->overtime_request_id,
            'approval_level' => $this->approval_level,
            'approver_type' => $this->approver_type,

            // Approver info (snapshot for audit trail)
            'approver_employee_id' => $this->approver_employee_id,
            'approver_name' => $this->approver_name,
            'approver_position' => $this->approver_position,

            // Current approver info (if still exists)
            'approver' => $this->whenLoaded('approverEmployee', fn () => [
                'id' => $this->approverEmployee->id,
                'employee_number' => $this->approverEmployee->employee_number,
                'full_name' => $this->approverEmployee->full_name,
            ]),

            // Decision
            'decision' => $this->decision->value,
            'decision_label' => $this->decision->label(),
            'decision_color' => $this->decision->color(),
            'remarks' => $this->remarks,
            'decided_at' => $this->decided_at?->format('Y-m-d H:i:s'),

            // Computed
            'is_pending' => $this->is_pending,
            'is_decided' => $this->is_decided,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
