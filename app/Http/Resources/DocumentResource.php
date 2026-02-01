<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Document $resource
 */
class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the latest version
        $latestVersion = $this->resource->versions
            ->sortByDesc('version_number')
            ->first();

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'category' => new DocumentCategoryResource($this->whenLoaded('category')),
            'current_version' => $latestVersion?->version_number ?? 1,
            'file_type' => $this->getFileType(),
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
            'file_size_formatted' => $this->formatFileSize($this->resource->file_size),
            'original_filename' => $this->resource->original_filename,
            'is_company_document' => $this->resource->is_company_document,
            'uploaded_at' => $this->resource->created_at?->toISOString(),
            'uploaded_by' => $latestVersion?->uploaded_by,
            'uploaded_by_name' => $this->resolveUploadedByName($latestVersion?->uploaded_by),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    /**
     * Get a simplified file type from MIME type.
     */
    protected function getFileType(): string
    {
        return match ($this->resource->mime_type) {
            'application/pdf' => 'PDF',
            'application/msword' => 'DOC',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
            'image/jpeg' => 'JPG',
            'image/png' => 'PNG',
            'application/vnd.ms-excel' => 'XLS',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
            default => strtoupper(pathinfo($this->resource->original_filename, PATHINFO_EXTENSION)),
        };
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
     * Resolve the name of the user who uploaded the document.
     *
     * Since uploaded_by is a cross-database reference to the platform users table,
     * we need to query it directly.
     */
    protected function resolveUploadedByName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);

        return $user?->name;
    }
}
