<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * KpiTemplate model for storing reusable KPI definitions.
 *
 * Templates define the base KPI structure (name, metric unit, default values)
 * that can be assigned to participants with specific targets and weights.
 */
class KpiTemplate extends TenantModel
{
    /** @use HasFactory<\Database\Factories\KpiTemplateFactory> */
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
        'metric_unit',
        'default_target',
        'default_weight',
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
            'default_target' => 'decimal:2',
            'default_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the KPI assignments for this template.
     */
    public function kpiAssignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class);
    }

    /**
     * Scope to filter only active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Create an assignment for a participant using this template.
     */
    public function createAssignment(
        PerformanceCycleParticipant $participant,
        ?float $target = null,
        ?float $weight = null
    ): KpiAssignment {
        return $this->kpiAssignments()->create([
            'performance_cycle_participant_id' => $participant->id,
            'target_value' => $target ?? $this->default_target ?? 0,
            'weight' => $weight ?? $this->default_weight ?? 1.00,
            'status' => 'pending',
        ]);
    }

    /**
     * Get the count of active assignments using this template.
     */
    public function getActiveAssignmentsCount(): int
    {
        return $this->kpiAssignments()
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
    }
}
