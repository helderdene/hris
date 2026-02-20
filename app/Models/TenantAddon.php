<?php

namespace App\Models;

use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAddon extends Model
{
    /** @use HasFactory<\Database\Factories\TenantAddonFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'quantity',
        'price_per_unit',
        'currency',
        'is_active',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
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
     * Get the tenant that owns this add-on.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Total extra units this add-on provides.
     */
    public function extraUnits(): int
    {
        return $this->quantity * $this->type->unitsPerQuantity();
    }

    /**
     * Monthly cost of this add-on in centavos.
     */
    public function monthlyCost(): int
    {
        return $this->quantity * $this->price_per_unit;
    }

    /**
     * Scope to only active, non-expired add-ons.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
