<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevelopmentPlanResource extends JsonResource
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
            'performance_cycle_participant_id' => $this->performance_cycle_participant_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->colorClass(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'target_completion_date' => $this->target_completion_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'career_path_notes' => $this->career_path_notes,
            'manager_id' => $this->manager_id,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'approval_notes' => $this->approval_notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed fields
            'progress' => $this->calculateProgress(),
            'is_editable' => $this->isEditable(),
            'can_add_activities' => $this->canAddActivities(),
            'is_overdue' => $this->isOverdue(),
            'days_remaining' => $this->getDaysRemaining(),

            // Relationships
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'position' => $this->employee->position?->title,
                'department' => $this->employee->department?->name,
            ]),
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'full_name' => $this->manager->full_name,
            ]),
            'approved_by_user' => $this->whenLoaded('approvedByUser', fn () => [
                'id' => $this->approvedByUser->id,
                'name' => $this->approvedByUser->name,
            ]),
            'created_by_user' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),
            'items' => DevelopmentPlanItemResource::collection($this->whenLoaded('items')),
            'check_ins' => DevelopmentPlanCheckInResource::collection($this->whenLoaded('checkIns')),
            'performance_cycle_participant' => $this->whenLoaded('performanceCycleParticipant', fn () => [
                'id' => $this->performanceCycleParticipant->id,
                'instance_name' => $this->performanceCycleParticipant->performanceCycleInstance?->name,
            ]),
        ];
    }
}
