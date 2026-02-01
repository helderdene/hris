<?php

namespace App\Models;

use App\Enums\ProbationaryMilestone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ProbationaryCriteriaTemplate model for configurable evaluation criteria.
 *
 * Stores the criteria templates that are used to evaluate probationary
 * employees at different milestones.
 */
class ProbationaryCriteriaTemplate extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ProbationaryCriteriaTemplateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'milestone',
        'name',
        'description',
        'weight',
        'sort_order',
        'min_rating',
        'max_rating',
        'is_active',
        'is_required',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milestone' => ProbationaryMilestone::class,
            'weight' => 'integer',
            'sort_order' => 'integer',
            'min_rating' => 'integer',
            'max_rating' => 'integer',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /**
     * Scope to filter only active criteria.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by milestone.
     */
    public function scopeForMilestone(Builder $query, ProbationaryMilestone|string $milestone): Builder
    {
        $value = $milestone instanceof ProbationaryMilestone ? $milestone->value : $milestone;

        return $query->where('milestone', $value);
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get active criteria for a specific milestone.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function getForMilestone(ProbationaryMilestone $milestone): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->active()
            ->forMilestone($milestone)
            ->ordered()
            ->get();
    }
}
