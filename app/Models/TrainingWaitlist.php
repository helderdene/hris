<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Training waitlist model for managing session waitlists.
 *
 * Supports FIFO ordering via position column.
 */
class TrainingWaitlist extends TenantModel
{
    /** @use HasFactory<\Database\Factories\TrainingWaitlistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'training_session_id',
        'employee_id',
        'status',
        'position',
        'joined_at',
        'promoted_at',
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
            'status' => WaitlistStatus::class,
            'joined_at' => 'datetime',
            'promoted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the training session this waitlist entry belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * Get the employee on the waitlist.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only waiting entries.
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', WaitlistStatus::Waiting->value);
    }

    /**
     * Scope to order by position (FIFO).
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    /**
     * Scope to get entries for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get the next position number for a session.
     */
    public static function getNextPosition(int $sessionId): int
    {
        $maxPosition = static::query()
            ->where('training_session_id', $sessionId)
            ->max('position');

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Mark this entry as promoted (converted to enrollment).
     */
    public function promote(): bool
    {
        if (! $this->status->isActive()) {
            return false;
        }

        $this->status = WaitlistStatus::Promoted;
        $this->promoted_at = now();

        return $this->save();
    }

    /**
     * Cancel this waitlist entry.
     */
    public function cancel(): bool
    {
        if (! $this->status->canBeCancelled()) {
            return false;
        }

        $this->status = WaitlistStatus::Cancelled;

        return $this->save();
    }

    /**
     * Mark this entry as expired.
     */
    public function expire(): bool
    {
        if (! $this->status->isActive()) {
            return false;
        }

        $this->status = WaitlistStatus::Expired;

        return $this->save();
    }

    /**
     * Check if the entry is active (waiting for promotion).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
