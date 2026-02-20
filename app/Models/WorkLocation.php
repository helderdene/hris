<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WorkLocation model for work location management.
 *
 * Includes JSON metadata field for flexible additional data storage.
 */
class WorkLocation extends TenantModel
{
    /** @use HasFactory<\Database\Factories\WorkLocationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
        'location_type',
        'timezone',
        'metadata',
        'status',
        'latitude',
        'longitude',
        'geofence_radius',
        'ip_whitelist',
        'location_check',
        'self_service_clockin_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location_type' => LocationType::class,
            'metadata' => 'array',
            'ip_whitelist' => 'array',
            'self_service_clockin_enabled' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Scope to get only active work locations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the biometric devices at this work location.
     */
    public function biometricDevices(): HasMany
    {
        return $this->hasMany(BiometricDevice::class);
    }

    /**
     * Get the kiosks at this work location.
     */
    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }
}
