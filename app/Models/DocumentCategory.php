<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DocumentCategory model for organizing documents.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * Supports both predefined system categories and custom tenant-specific categories.
 */
class DocumentCategory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DocumentCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_predefined',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_predefined' => 'boolean',
        ];
    }

    /**
     * Get the documents belonging to this category.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
