<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\CertificationFile $resource
 */
class CertificationFileResource extends JsonResource
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
            'certification_id' => $this->resource->certification_id,
            'original_filename' => $this->resource->original_filename,
            'mime_type' => $this->resource->mime_type,
            'file_size' => $this->resource->file_size,
            'file_size_formatted' => $this->resource->formatted_file_size,
            'uploaded_by' => $this->resource->uploaded_by,
            'uploaded_by_name' => $this->resolveUploadedByName(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }

    /**
     * Resolve the name of the user who uploaded this file.
     */
    protected function resolveUploadedByName(): ?string
    {
        if ($this->resource->uploaded_by === null) {
            return null;
        }

        try {
            $user = app()->environment('testing')
                ? User::find($this->resource->uploaded_by)
                : User::on('mysql')->find($this->resource->uploaded_by);

            return $user?->name ?? 'Unknown User';
        } catch (\Exception $e) {
            return 'Unknown User';
        }
    }
}
