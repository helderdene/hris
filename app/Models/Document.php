<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document model for managing employee and company documents.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * Supports soft deletes to preserve document history.
 * Can be associated with an employee or marked as a company-wide document.
 */
class Document extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'document_category_id',
        'name',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'is_company_document',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_company_document' => 'boolean',
        ];
    }

    /**
     * Get the category this document belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * Get the employee this document belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the versions of this document.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Get the latest version of this document.
     */
    public function latestVersion(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number')->limit(1);
    }

    /**
     * Get the URL to preview/display this document (serves actual file).
     */
    public function getUrl(): ?string
    {
        $tenant = tenant();
        if (! $tenant) {
            return null;
        }

        // Get the latest version to build the preview URL
        $latestVersion = $this->versions()->orderByDesc('version_number')->first();
        if (! $latestVersion) {
            return null;
        }

        return route('api.documents.versions.preview', [
            'tenant' => $tenant->slug,
            'document' => $this->id,
            'version' => $latestVersion->id,
        ]);
    }
}
