<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LeaveBalance model for tracking employee leave credits.
 *
 * Each employee has one balance record per leave type per year.
 * Tracks brought forward, earned, used, pending, adjustments, and expired days.
 */
class LeaveBalance extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LeaveBalanceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'brought_forward',
        'earned',
        'used',
        'pending',
        'adjustments',
        'expired',
        'carry_over_expiry_date',
        'last_accrual_at',
        'year_end_processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'brought_forward' => 'decimal:2',
            'earned' => 'decimal:2',
            'used' => 'decimal:2',
            'pending' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'expired' => 'decimal:2',
            'carry_over_expiry_date' => 'date',
            'last_accrual_at' => 'datetime',
            'year_end_processed_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this balance belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave type this balance is for.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get all adjustment history records for this balance.
     */
    public function adjustmentHistory(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class)->orderByDesc('created_at');
    }

    /**
     * Get the total credits available (before usage).
     */
    protected function totalCredits(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                return round(
                    (float) $this->brought_forward
                    + (float) $this->earned
                    + (float) $this->adjustments
                    - (float) $this->expired,
                    2
                );
            }
        );
    }

    /**
     * Get the available balance (can be used for leave requests).
     */
    protected function available(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                return round(
                    (float) $this->brought_forward
                    + (float) $this->earned
                    + (float) $this->adjustments
                    - (float) $this->used
                    - (float) $this->pending
                    - (float) $this->expired,
                    2
                );
            }
        );
    }

    /**
     * Scope to filter balances for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter balances for a specific year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter balances for a specific leave type.
     */
    public function scopeForLeaveType(Builder $query, int|LeaveType $leaveType): Builder
    {
        $leaveTypeId = $leaveType instanceof LeaveType ? $leaveType->id : $leaveType;

        return $query->where('leave_type_id', $leaveTypeId);
    }

    /**
     * Scope to find balances with expired carry-over that hasn't been processed.
     */
    public function scopeWithExpiredCarryOver(Builder $query): Builder
    {
        return $query->whereNotNull('carry_over_expiry_date')
            ->where('carry_over_expiry_date', '<=', now()->toDateString())
            ->where('brought_forward', '>', 0)
            ->where(function ($q) {
                // Only include if brought_forward hasn't been fully expired
                $q->whereRaw('brought_forward > expired');
            });
    }

    /**
     * Check if there is sufficient available balance for a leave request.
     */
    public function hasAvailableBalance(float $days): bool
    {
        return $this->available >= $days;
    }

    /**
     * Record usage when leave is approved.
     */
    public function recordUsage(float $days): void
    {
        $this->used = (float) $this->used + $days;
        $this->save();
    }

    /**
     * Reserve balance when leave request is submitted (pending).
     */
    public function recordPending(float $days): void
    {
        $this->pending = (float) $this->pending + $days;
        $this->save();
    }

    /**
     * Release reserved balance when leave request is cancelled or rejected.
     */
    public function releasePending(float $days): void
    {
        $this->pending = max(0, (float) $this->pending - $days);
        $this->save();
    }

    /**
     * Convert pending to used when leave request is approved.
     */
    public function convertPendingToUsed(float $days): void
    {
        $this->pending = max(0, (float) $this->pending - $days);
        $this->used = (float) $this->used + $days;
        $this->save();
    }

    /**
     * Record expired carry-over balance.
     */
    public function expireCarryOver(): void
    {
        if ($this->brought_forward > 0 && $this->carry_over_expiry_date !== null) {
            $toExpire = (float) $this->brought_forward - (float) $this->expired;
            if ($toExpire > 0) {
                $this->expired = (float) $this->expired + $toExpire;
                $this->save();
            }
        }
    }
}
