<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BackgroundCheckDocument
 */
class BackgroundCheckDocumentResource extends JsonResource
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
            'background_check_id' => $this->background_check_id,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
