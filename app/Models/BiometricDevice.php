<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BiometricDevice model for managing MQTT-enabled facial recognition devices.
 *
 * Tracks device connection status, uptime, and associates devices with work locations.
 */
class BiometricDevice extends TenantModel
{
    /** @use HasFactory<\Database\Factories\BiometricDeviceFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'offline',
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'device_identifier',
        'work_location_id',
        'status',
        'last_seen_at',
        'connection_started_at',
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
            'status' => DeviceStatus::class,
            'last_seen_at' => 'datetime',
            'connection_started_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the work location that this device belongs to.
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Get the employee device sync records for this device.
     */
    public function employeeDeviceSyncs(): HasMany
    {
        return $this->hasMany(EmployeeDeviceSync::class);
    }

    /**
     * Get the device sync logs for this device.
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(DeviceSyncLog::class);
    }

    /**
     * Scope to get only active devices.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the device uptime in seconds.
     */
    protected function uptimeSeconds(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->connection_started_at
                ? (int) Carbon::now()->diffInSeconds($this->connection_started_at, absolute: true)
                : null,
        );
    }

    /**
     * Get the device uptime in human-readable format.
     */
    protected function uptimeHuman(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->connection_started_at
                ? $this->connection_started_at->diffForHumans(Carbon::now(), [
                    'syntax' => Carbon::DIFF_ABSOLUTE,
                    'parts' => 2,
                ])
                : null,
        );
    }
}
