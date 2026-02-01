<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Goal $resource
 */
class GoalResource extends JsonResource
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
            'performance_cycle_instance_id' => $this->resource->performance_cycle_instance_id,
            'parent_goal_id' => $this->resource->parent_goal_id,
            'goal_type' => $this->resource->goal_type?->value,
            'goal_type_label' => $this->resource->goal_type?->label(),
            'goal_type_color' => $this->resource->goal_type?->colorClass(),
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'category' => $this->resource->category,
            'visibility' => $this->resource->visibility?->value,
            'visibility_label' => $this->resource->visibility?->label(),
            'visibility_color' => $this->resource->visibility?->colorClass(),
            'priority' => $this->resource->priority?->value,
            'priority_label' => $this->resource->priority?->label(),
            'priority_color' => $this->resource->priority?->colorClass(),
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'status_color' => $this->resource->status?->colorClass(),
            'approval_status' => $this->resource->approval_status?->value,
            'approval_status_label' => $this->resource->approval_status?->label(),
            'approval_status_color' => $this->resource->approval_status?->colorClass(),
            'approved_by' => $this->resource->approved_by,
            'approved_at' => $this->resource->approved_at?->toISOString(),
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'due_date' => $this->resource->due_date?->format('Y-m-d'),
            'completed_at' => $this->resource->completed_at?->toISOString(),
            'progress_percentage' => $this->resource->progress_percentage,
            'weight' => $this->resource->weight,
            'final_score' => $this->resource->final_score,
            'owner_notes' => $this->resource->owner_notes,
            'manager_feedback' => $this->resource->manager_feedback,
            'is_overdue' => $this->resource->isOverdue(),
            'days_remaining' => $this->resource->getDaysRemaining(),
            'employee' => $this->when(
                $this->resource->relationLoaded('employee'),
                fn () => $this->formatEmployee()
            ),
            'parent_goal' => $this->when(
                $this->resource->relationLoaded('parentGoal'),
                fn () => $this->formatParentGoal()
            ),
            'child_goals' => $this->when(
                $this->resource->relationLoaded('childGoals'),
                fn () => GoalListResource::collection($this->resource->childGoals)
            ),
            'key_results' => $this->when(
                $this->resource->relationLoaded('keyResults'),
                fn () => GoalKeyResultResource::collection($this->resource->keyResults)
            ),
            'milestones' => $this->when(
                $this->resource->relationLoaded('milestones'),
                fn () => GoalMilestoneResource::collection($this->resource->milestones)
            ),
            'progress_entries' => $this->when(
                $this->resource->relationLoaded('progressEntries'),
                fn () => GoalProgressEntryResource::collection($this->resource->progressEntries)
            ),
            'comments' => $this->when(
                $this->resource->relationLoaded('comments'),
                fn () => GoalCommentResource::collection($this->resource->comments)
            ),
            'approved_by_user' => $this->when(
                $this->resource->relationLoaded('approvedByUser'),
                fn () => $this->formatApprovedByUser()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format employee data.
     *
     * @return array<string, mixed>
     */
    protected function formatEmployee(): array
    {
        $employee = $this->resource->employee;

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name ?? null,
            'position_id' => $employee->position_id,
            'position_title' => $employee->position?->title ?? null,
        ];
    }

    /**
     * Format parent goal data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatParentGoal(): ?array
    {
        $parent = $this->resource->parentGoal;

        if ($parent === null) {
            return null;
        }

        return [
            'id' => $parent->id,
            'title' => $parent->title,
            'goal_type' => $parent->goal_type?->value,
            'goal_type_label' => $parent->goal_type?->label(),
            'status' => $parent->status?->value,
            'status_label' => $parent->status?->label(),
            'progress_percentage' => $parent->progress_percentage,
        ];
    }

    /**
     * Format approved by user data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatApprovedByUser(): ?array
    {
        $user = $this->resource->approvedByUser;

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
