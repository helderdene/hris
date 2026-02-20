<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kiosk model for managing shared attendance terminals.
 *
 * Kiosks are physical terminals placed at work locations where employees
 * can clock in/out using a PIN. Access is via a unique token URL.
 */
class Kiosk extends TenantModel
{
    /** @use HasFactory<\Database\Factories\KioskFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'token',
        'location',
        'work_location_id',
        'ip_whitelist',
        'settings',
        'is_active',
        'last_activity_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ip_whitelist' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the work location this kiosk belongs to.
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Get the attendance logs recorded through this kiosk.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * Scope to get only active kiosks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the cooldown period in minutes from kiosk settings.
     */
    public function getCooldownMinutes(): int
    {
        return (int) ($this->settings['cooldown_minutes'] ?? 5);
    }
}
