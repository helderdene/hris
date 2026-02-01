<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Announcement $resource
 */
class AnnouncementResource extends JsonResource
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
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'published_at' => $this->resource->published_at?->toISOString(),
            'formatted_published_at' => $this->resource->published_at?->format('M d, Y h:i A'),
            'expires_at' => $this->resource->expires_at?->toISOString(),
            'formatted_expires_at' => $this->resource->expires_at?->format('M d, Y h:i A'),
            'is_pinned' => $this->resource->is_pinned,
            'created_by' => $this->resource->created_by,
            'creator_name' => $this->getCreatorName(),
            'status' => $this->getStatus(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Get the creator name from the main database connection.
     */
    protected function getCreatorName(): ?string
    {
        if (! $this->resource->created_by) {
            return null;
        }

        return User::find($this->resource->created_by)?->name;
    }

    /**
     * Compute the announcement status.
     */
    protected function getStatus(): string
    {
        if ($this->resource->published_at === null) {
            return 'draft';
        }

        if ($this->resource->published_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->resource->expires_at && $this->resource->expires_at->isPast()) {
            return 'expired';
        }

        return 'published';
    }
}
