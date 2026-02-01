<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CertificationType model for defining types of professional certifications.
 *
 * Includes configuration for validity periods and expiry reminder schedules.
 */
class CertificationType extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CertificationTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'validity_period_months',
        'reminder_days_before_expiry',
        'is_mandatory',
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
            'validity_period_months' => 'integer',
            'reminder_days_before_expiry' => 'array',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all certifications of this type.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    /**
     * Scope to only active certification types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only mandatory certification types.
     */
    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Get reminder days as a sorted array.
     *
     * @return array<int>
     */
    public function getReminderDaysSorted(): array
    {
        $days = $this->reminder_days_before_expiry ?? [];

        rsort($days);

        return $days;
    }
}
