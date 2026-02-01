<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\AuditLog $resource
 */
class AuditLogResource extends JsonResource
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
            'auditable_type' => $this->resource->auditable_type,
            'auditable_id' => $this->resource->auditable_id,
            'model_name' => $this->resource->model_name,
            'action' => $this->resource->action->value,
            'action_label' => $this->resource->action->label(),
            'action_color' => $this->resource->action->color(),
            'user_id' => $this->resource->user_id,
            'user_name' => $this->getUserName(),
            'old_values' => $this->resource->old_values,
            'new_values' => $this->resource->new_values,
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'created_at' => $this->resource->created_at?->toISOString(),
            'formatted_created_at' => $this->resource->created_at?->format('M d, Y h:i A'),
        ];
    }

    /**
     * Get the user name from the main database connection.
     */
    protected function getUserName(): ?string
    {
        if (! $this->resource->user_id) {
            return 'System';
        }

        return User::find($this->resource->user_id)?->name ?? 'Unknown User';
    }
}
