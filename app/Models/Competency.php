<?php

namespace App\Models;

use App\Enums\CompetencyCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Competency model for storing reusable competency definitions.
 *
 * Competencies define behavioral or technical skills that can be
 * assigned to positions with specific proficiency expectations.
 */
class Competency extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CompetencyFactory> */
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
        'category',
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
            'category' => CompetencyCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the position competency assignments for this competency.
     */
    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(PositionCompetency::class);
    }

    /**
     * Scope to filter only active competencies.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory(Builder $query, CompetencyCategory|string $category): Builder
    {
        $value = $category instanceof CompetencyCategory ? $category->value : $category;

        return $query->where('category', $value);
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
     * Get the count of positions using this competency.
     */
    public function getPositionsCount(): int
    {
        return $this->positionCompetencies()
            ->distinct('position_id')
            ->count('position_id');
    }
}
