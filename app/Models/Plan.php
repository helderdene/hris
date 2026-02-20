<?php

namespace App\Models;

use App\Enums\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_custom',
        'tenant_id',
        'sort_order',
        'limits',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            return null;
        }

        return 'platform';
    }

    /**
     * Get the prices for this plan.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * Get the module assignments for this plan.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    /**
     * Get the tenants subscribed to this plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Check if this plan includes a given module.
     */
    public function hasModule(Module $module): bool
    {
        return $this->modules->contains('module', $module->value);
    }

    /**
     * Get a specific limit value from the limits JSON.
     */
    public function getLimit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }

    /**
     * Scope to only active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only standard (non-custom) plans.
     */
    public function scopeStandard(Builder $query): Builder
    {
        return $query->where('is_custom', false);
    }
}
