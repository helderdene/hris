<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\GoalComment $resource
 */
class GoalCommentResource extends JsonResource
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
            'goal_id' => $this->resource->goal_id,
            'user_id' => $this->resource->user_id,
            'comment' => $this->resource->comment,
            'is_private' => $this->resource->is_private,
            'user' => $this->when(
                $this->resource->relationLoaded('user'),
                fn () => $this->formatUser()
            ),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Format user data.
     *
     * @return array<string, mixed>|null
     */
    protected function formatUser(): ?array
    {
        $user = $this->resource->user;

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
