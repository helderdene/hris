<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\DocumentVersion $resource
 */
class DocumentVersionResource extends JsonResource
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
            'version_number' => $this->resource->version_number,
            'file_size' => $this->resource->file_size,
            'file_size_formatted' => $this->formatFileSize($this->resource->file_size),
            'mime_type' => $this->resource->mime_type,
            'uploaded_at' => $this->resource->created_at?->toISOString(),
            'uploaded_by' => $this->resource->uploaded_by,
            'uploaded_by_name' => $this->resolveUploadedByName(),
            'version_notes' => $this->resource->version_notes,
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Format file size to human-readable format.
     */
    protected function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2).' '.$units[$pow];
    }

    /**
     * Resolve the name of the user who uploaded this version.
     *
     * Since uploaded_by is a cross-database reference to the platform users table,
     * we need to query it directly. In production, this uses the platform connection.
     * In tests, uses default connection since databases may be different.
     */
    protected function resolveUploadedByName(): ?string
    {
        if ($this->resource->uploaded_by === null) {
            return null;
        }

        try {
            // In testing environment, use default connection
            // In production, use platform (mysql) connection
            $user = app()->environment('testing')
                ? User::find($this->resource->uploaded_by)
                : User::on('mysql')->find($this->resource->uploaded_by);

            return $user?->name ?? 'Unknown User';
        } catch (\Exception $e) {
            // Fallback if connection fails
            return 'Unknown User';
        }
    }
}
