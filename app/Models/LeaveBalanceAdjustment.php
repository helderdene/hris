<?php

namespace App\Models;

use App\Enums\LeaveBalanceAdjustmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LeaveBalanceAdjustment model for audit trail of balance changes.
 *
 * Records all manual adjustments made by HR with reason and before/after values.
 * Supports optional references to related entities (leave requests, year-end processing).
 */
class LeaveBalanceAdjustment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LeaveBalanceAdjustmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'leave_balance_id',
        'adjusted_by',
        'adjustment_type',
        'days',
        'reason',
        'previous_balance',
        'new_balance',
        'reference_type',
        'reference_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_type' => LeaveBalanceAdjustmentType::class,
            'days' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'new_balance' => 'decimal:2',
        ];
    }

    /**
     * Get the leave balance this adjustment belongs to.
     */
    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    /**
     * Get the user who made this adjustment.
     */
    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * Scope to filter only credit adjustments.
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('adjustment_type', LeaveBalanceAdjustmentType::Credit);
    }

    /**
     * Scope to filter only debit adjustments.
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('adjustment_type', LeaveBalanceAdjustmentType::Debit);
    }

    /**
     * Scope to filter adjustments by reference.
     */
    public function scopeByReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    /**
     * Check if this is a credit adjustment.
     */
    public function isCredit(): bool
    {
        return $this->adjustment_type === LeaveBalanceAdjustmentType::Credit;
    }

    /**
     * Check if this is a debit adjustment.
     */
    public function isDebit(): bool
    {
        return $this->adjustment_type === LeaveBalanceAdjustmentType::Debit;
    }

    /**
     * Get the signed days value (positive for credits, negative for debits).
     */
    public function getSignedDays(): float
    {
        return (float) $this->days * $this->adjustment_type->sign();
    }
}
