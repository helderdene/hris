<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class HelpArticle extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'help_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'related_article_ids',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'related_article_ids' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'view_count' => 'integer',
        ];
    }

    /**
     * Get the category this article belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    /**
     * Get the related articles.
     *
     * @return Collection<int, HelpArticle>
     */
    public function relatedArticles(): Collection
    {
        if (empty($this->related_article_ids)) {
            return collect();
        }

        return static::whereIn('id', $this->related_article_ids)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Increment the view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Scope to only active articles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only featured articles.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to order articles by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope for full-text search on title, excerpt, and content.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if (config('database.default') === 'sqlite') {
            // SQLite fallback: use LIKE search
            return $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        // MySQL full-text search
        return $query->whereRaw(
            'MATCH(title, excerpt, content) AGAINST(? IN NATURAL LANGUAGE MODE)',
            [$term]
        );
    }
}
