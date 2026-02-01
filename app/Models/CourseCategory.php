<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CourseCategory model for organizing training courses.
 *
 * Supports hierarchical parent-child relationships through self-referential parent_id.
 */
class CourseCategory extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CourseCategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(CourseCategory::class, 'parent_id');
    }

    /**
     * Get the courses in this category.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_category_course')
            ->withTimestamps();
    }

    /**
     * Scope to filter only active categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only root categories (no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to search by name or code.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * Validate that setting a parent_id would not create a circular reference.
     */
    public function validateNotCircularReference(?int $newParentId): bool
    {
        if ($newParentId === null) {
            return true;
        }

        if ($this->id !== null && $newParentId === $this->id) {
            return false;
        }

        if ($this->id !== null && $this->isDescendant($newParentId)) {
            return false;
        }

        return true;
    }

    /**
     * Check if current parent_id would create a circular reference.
     */
    public function isCircularReference(): bool
    {
        return ! $this->validateNotCircularReference($this->parent_id);
    }

    /**
     * Check if a category is a descendant of this category.
     */
    protected function isDescendant(int $categoryId): bool
    {
        $children = $this->children()->get();

        foreach ($children as $child) {
            if ($child->id === $categoryId) {
                return true;
            }

            if ($child->isDescendant($categoryId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the count of courses in this category.
     */
    public function getCoursesCount(): int
    {
        return $this->courses()->count();
    }
}
