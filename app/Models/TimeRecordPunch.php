<?php

namespace App\Models;

use App\Enums\PunchType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TimeRecordPunch model for linking DTR to raw attendance log entries.
 *
 * Tracks individual IN/OUT punches with validation status.
 */
class TimeRecordPunch extends TenantModel
{
    /** @use HasFactory<\Database\Factories\TimeRecordPunchFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'daily_time_record_id',
        'attendance_log_id',
        'punch_type',
        'punched_at',
        'is_valid',
        'invalidation_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punch_type' => PunchType::class,
            'punched_at' => 'datetime',
            'is_valid' => 'boolean',
        ];
    }

    /**
     * Get the daily time record this punch belongs to.
     */
    public function dailyTimeRecord(): BelongsTo
    {
        return $this->belongsTo(DailyTimeRecord::class);
    }

    /**
     * Get the original attendance log entry.
     */
    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    /**
     * Scope to get only valid punches.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope to filter by punch type.
     */
    public function scopeOfType(Builder $query, PunchType $type): Builder
    {
        return $query->where('punch_type', $type);
    }

    /**
     * Scope to get time-in punches.
     */
    public function scopeTimeIn(Builder $query): Builder
    {
        return $query->where('punch_type', PunchType::In);
    }

    /**
     * Scope to get time-out punches.
     */
    public function scopeTimeOut(Builder $query): Builder
    {
        return $query->where('punch_type', PunchType::Out);
    }
}
