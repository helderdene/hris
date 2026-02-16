<?php

namespace App\Models;

use App\Enums\DtrStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DailyTimeRecord model for storing computed daily attendance records.
 *
 * Summarizes raw AttendanceLog punches into daily time records with
 * computed late, undertime, overtime, and night differential values.
 */
class DailyTimeRecord extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DailyTimeRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'work_schedule_id',
        'shift_name',
        'status',
        'first_in',
        'last_out',
        'total_work_minutes',
        'total_break_minutes',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'overtime_approved',
        'overtime_denied',
        'overtime_request_id',
        'night_diff_minutes',
        'remarks',
        'needs_review',
        'review_reason',
        'computed_at',
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
            'first_in' => 'datetime',
            'last_out' => 'datetime',
            'status' => DtrStatus::class,
            'total_work_minutes' => 'integer',
            'total_break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'overtime_approved' => 'boolean',
            'overtime_denied' => 'boolean',
            'night_diff_minutes' => 'integer',
            'needs_review' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this record belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the work schedule captured at time of record.
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * Get the overtime request linked to this DTR.
     */
    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    /**
     * Get the punch records linked to this DTR.
     */
    public function punches(): HasMany
    {
        return $this->hasMany(TimeRecordPunch::class)->orderBy('punched_at');
    }

    /**
     * Scope to filter records by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter records by date range.
     */
    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Scope to get only records needing review.
     */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('needs_review', true);
    }

    /**
     * Scope to get only records with unapproved overtime.
     */
    public function scopeWithUnapprovedOvertime(Builder $query): Builder
    {
        return $query->where('overtime_minutes', '>', 0)
            ->where('overtime_approved', false);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, DtrStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Get total work hours as a decimal.
     */
    public function getTotalWorkHoursAttribute(): float
    {
        return round($this->total_work_minutes / 60, 2);
    }

    /**
     * Get late duration as formatted string.
     */
    public function getLateFormattedAttribute(): string
    {
        return $this->formatMinutes($this->late_minutes);
    }

    /**
     * Get undertime duration as formatted string.
     */
    public function getUndertimeFormattedAttribute(): string
    {
        return $this->formatMinutes($this->undertime_minutes);
    }

    /**
     * Get overtime duration as formatted string.
     */
    public function getOvertimeFormattedAttribute(): string
    {
        return $this->formatMinutes($this->overtime_minutes);
    }

    /**
     * Format minutes into hours:minutes string.
     */
    protected function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
