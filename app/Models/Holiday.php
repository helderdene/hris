<?php

namespace App\Models;

use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Holiday model for Philippine holiday calendar management.
 *
 * Supports national holidays and location-specific regional holidays.
 * Extends TenantModel for multi-tenant database isolation.
 */
class Holiday extends TenantModel
{
    /** @use HasFactory<\Database\Factories\HolidayFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'date',
        'holiday_type',
        'description',
        'is_national',
        'year',
        'work_location_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'holiday_type' => HolidayType::class,
            'is_national' => 'boolean',
            'year' => 'integer',
        ];
    }

    /**
     * Get the work location that this holiday applies to.
     *
     * Only applicable for regional/local holidays (is_national = false).
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Scope to filter holidays by year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get only national holidays.
     */
    public function scopeNational(Builder $query): Builder
    {
        return $query->where('is_national', true);
    }

    /**
     * Scope to filter holidays by work location.
     */
    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('work_location_id', $locationId);
    }

    /**
     * Scope to filter holidays by date range.
     */
    public function scopeForDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
