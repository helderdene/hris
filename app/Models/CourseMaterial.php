<?php

namespace App\Models;

use App\Enums\CourseMaterialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Course material model for training course attachments.
 *
 * Stores documents, videos, images, and external links attached to courses.
 */
class CourseMaterial extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CourseMaterialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'material_type',
        'external_url',
        'sort_order',
        'uploaded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_type' => CourseMaterialType::class,
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the course this material belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the employee who uploaded this material.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    /**
     * Scope to filter by material type.
     */
    public function scopeOfType(Builder $query, CourseMaterialType|string $type): Builder
    {
        $value = $type instanceof CourseMaterialType ? $type->value : $type;

        return $query->where('material_type', $value);
    }

    /**
     * Scope to get only document materials.
     */
    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('material_type', CourseMaterialType::Document->value);
    }

    /**
     * Scope to get only video materials.
     */
    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('material_type', CourseMaterialType::Video->value);
    }

    /**
     * Scope to get only image materials.
     */
    public function scopeImages(Builder $query): Builder
    {
        return $query->where('material_type', CourseMaterialType::Image->value);
    }

    /**
     * Scope to get only link materials.
     */
    public function scopeLinks(Builder $query): Builder
    {
        return $query->where('material_type', CourseMaterialType::Link->value);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get the download URL for this material.
     */
    public function getDownloadUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        return route('api.training.materials.download', [
            'tenant' => $tenant->slug,
            'material' => $this->id,
        ]);
    }

    /**
     * Check if this material has an uploaded file.
     */
    public function hasFile(): bool
    {
        return $this->file_path !== null && $this->file_name !== null;
    }

    /**
     * Check if this material is an external link.
     */
    public function isExternalLink(): bool
    {
        return $this->material_type === CourseMaterialType::Link;
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }
}
