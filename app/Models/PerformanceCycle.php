<?php

namespace App\Models;

use App\Enums\PerformanceCycleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PerformanceCycle model for defining performance evaluation cycle configurations.
 *
 * Each cycle represents a recurring evaluation pattern (e.g., annual, mid-year)
 * that can be used to generate specific evaluation instances.
 */
class PerformanceCycle extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PerformanceCycleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'cycle_type',
        'description',
        'status',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle_type' => PerformanceCycleType::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the performance cycle instances that belong to this cycle.
     */
    public function performanceCycleInstances(): HasMany
    {
        return $this->hasMany(PerformanceCycleInstance::class);
    }

    /**
     * Scope to filter only active cycles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get the default cycle.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by cycle type.
     */
    public function scopeOfType(Builder $query, PerformanceCycleType $type): Builder
    {
        return $query->where('cycle_type', $type);
    }

    /**
     * Set this cycle as the default, unsetting any other default.
     */
    public function setAsDefault(): void
    {
        static::query()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Check if this cycle generates recurring instances.
     */
    public function isRecurring(): bool
    {
        return $this->cycle_type->isRecurring();
    }

    /**
     * Get the expected number of instances per year for this cycle.
     */
    public function getInstancesPerYear(): ?int
    {
        return $this->cycle_type->instancesPerYear();
    }
}
