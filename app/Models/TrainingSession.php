<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Training session model for scheduled course sessions.
 *
 * Links to courses and manages enrollments with capacity tracking.
 */
class TrainingSession extends TenantModel
{
    /** @use HasFactory<\Database\Factories\TrainingSessionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'location',
        'virtual_link',
        'status',
        'max_participants',
        'notes',
        'instructor_employee_id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'status' => SessionStatus::class,
        ];
    }

    /**
     * Get the course this session belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the instructor for this session.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'instructor_employee_id');
    }

    /**
     * Get the employee who created this session.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the enrollments for this session.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    /**
     * Get the active (confirmed) enrollments for this session.
     */
    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class)
            ->where('status', EnrollmentStatus::Confirmed->value);
    }

    /**
     * Get the waitlist entries for this session.
     */
    public function waitlist(): HasMany
    {
        return $this->hasMany(TrainingWaitlist::class)->orderBy('position');
    }

    /**
     * Get the active waitlist entries for this session.
     */
    public function activeWaitlist(): HasMany
    {
        return $this->waitlist()->waiting();
    }

    /**
     * Scope to get only scheduled sessions (available for enrollment).
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', SessionStatus::Scheduled->value);
    }

    /**
     * Scope to get upcoming sessions (start date is in the future).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>=', now()->startOfDay());
    }

    /**
     * Scope to get sessions within a specific month.
     */
    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, SessionStatus|string $status): Builder
    {
        $value = $status instanceof SessionStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to filter by course.
     */
    public function scopeForCourse(Builder $query, int|Course $course): Builder
    {
        $courseId = $course instanceof Course ? $course->id : $course;

        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to get sessions visible to employees.
     */
    public function scopeVisibleToEmployees(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SessionStatus::Scheduled->value,
            SessionStatus::InProgress->value,
            SessionStatus::Completed->value,
        ]);
    }

    /**
     * Get the display title for this session.
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->title ?? $this->course?->title ?? 'Untitled Session'
        );
    }

    /**
     * Get the count of active enrollments.
     */
    protected function enrolledCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->activeEnrollments()->count()
        );
    }

    /**
     * Get the effective max participants (falls back to course setting).
     */
    protected function effectiveMaxParticipants(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->max_participants ?? $this->course?->max_participants
        );
    }

    /**
     * Get the number of available slots.
     */
    protected function availableSlots(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                $max = $this->effective_max_participants;

                if ($max === null) {
                    return null;
                }

                return max(0, $max - $this->enrolled_count);
            }
        );
    }

    /**
     * Check if the session is full.
     */
    protected function isFull(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $max = $this->effective_max_participants;

                if ($max === null) {
                    return false;
                }

                return $this->enrolled_count >= $max;
            }
        );
    }

    /**
     * Get the formatted date range.
     */
    protected function dateRange(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->start_date->isSameDay($this->end_date)) {
                    return $this->start_date->format('M j, Y');
                }

                return $this->start_date->format('M j').' - '.$this->end_date->format('M j, Y');
            }
        );
    }

    /**
     * Get the formatted time range.
     */
    protected function timeRange(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->start_time && ! $this->end_time) {
                    return null;
                }

                $start = $this->start_time ? Carbon::parse($this->start_time)->format('g:i A') : null;
                $end = $this->end_time ? Carbon::parse($this->end_time)->format('g:i A') : null;

                if ($start && $end) {
                    return "{$start} - {$end}";
                }

                return $start ?? $end;
            }
        );
    }

    /**
     * Check if an employee is enrolled in this session.
     */
    public function hasEmployee(Employee|int $employee): bool
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->enrollments()
            ->where('employee_id', $employeeId)
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->exists();
    }

    /**
     * Check if an employee is on the waitlist for this session.
     */
    public function hasEmployeeOnWaitlist(Employee|int $employee): bool
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->waitlist()
            ->where('employee_id', $employeeId)
            ->waiting()
            ->exists();
    }

    /**
     * Publish the session (make it available for enrollment).
     */
    public function publish(): bool
    {
        if ($this->status !== SessionStatus::Draft) {
            return false;
        }

        $this->status = SessionStatus::Scheduled;

        return $this->save();
    }

    /**
     * Cancel the session.
     */
    public function cancel(): bool
    {
        if (! $this->status->canBeCancelled()) {
            return false;
        }

        $this->status = SessionStatus::Cancelled;

        return $this->save();
    }

    /**
     * Mark the session as in progress.
     */
    public function markAsInProgress(): bool
    {
        if ($this->status !== SessionStatus::Scheduled) {
            return false;
        }

        $this->status = SessionStatus::InProgress;

        return $this->save();
    }

    /**
     * Mark the session as completed.
     */
    public function markAsCompleted(): bool
    {
        if (! in_array($this->status, [SessionStatus::Scheduled, SessionStatus::InProgress], true)) {
            return false;
        }

        $this->status = SessionStatus::Completed;

        return $this->save();
    }
}
