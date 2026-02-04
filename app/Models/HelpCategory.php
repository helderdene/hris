<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpCategory extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get all articles in this category.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class);
    }

    /**
     * Get only active articles in this category, ordered by sort_order.
     */
    public function activeArticles(): HasMany
    {
        return $this->hasMany(HelpArticle::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Scope to only active categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order categories by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
