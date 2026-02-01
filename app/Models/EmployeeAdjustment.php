<?php

namespace App\Models;

use App\Enums\AdjustmentCategory;
use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee adjustment model for tracking payroll adjustments.
 *
 * Supports various adjustment types including allowances, bonuses,
 * deductions, and loans with optional balance tracking.
 */
class EmployeeAdjustment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeAdjustmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'adjustment_category',
        'adjustment_type',
        'adjustment_code',
        'name',
        'description',
        'amount',
        'is_taxable',
        'frequency',
        'recurring_start_date',
        'recurring_end_date',
        'recurring_interval',
        'remaining_occurrences',
        'has_balance_tracking',
        'total_amount',
        'total_applied',
        'remaining_balance',
        'target_payroll_period_id',
        'status',
        'notes',
        'metadata',
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
            'adjustment_category' => AdjustmentCategory::class,
            'adjustment_type' => AdjustmentType::class,
            'frequency' => AdjustmentFrequency::class,
            'recurring_interval' => RecurringInterval::class,
            'status' => AdjustmentStatus::class,
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_applied' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'is_taxable' => 'boolean',
            'has_balance_tracking' => 'boolean',
            'remaining_occurrences' => 'integer',
            'recurring_start_date' => 'date',
            'recurring_end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the employee this adjustment belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the target payroll period for one-time adjustments.
     */
    public function targetPayrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'target_payroll_period_id');
    }

    /**
     * Get the user who created this adjustment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the applications of this adjustment.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(AdjustmentApplication::class);
    }

    /**
     * Scope to get only active adjustments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AdjustmentStatus::Active);
    }

    /**
     * Scope to filter adjustments for a specific employee.
     */
    public function scopeForEmployee(Builder $query, Employee|int $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeOfCategory(Builder $query, AdjustmentCategory|string $category): Builder
    {
        $categoryValue = $category instanceof AdjustmentCategory ? $category->value : $category;

        return $query->where('adjustment_category', $categoryValue);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType(Builder $query, AdjustmentType|string $type): Builder
    {
        $typeValue = $type instanceof AdjustmentType ? $type->value : $type;

        return $query->where('adjustment_type', $typeValue);
    }

    /**
     * Scope to get only earning adjustments.
     */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('adjustment_category', AdjustmentCategory::Earning);
    }

    /**
     * Scope to get only deduction adjustments.
     */
    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('adjustment_category', AdjustmentCategory::Deduction);
    }

    /**
     * Scope to get only one-time adjustments.
     */
    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('frequency', AdjustmentFrequency::OneTime);
    }

    /**
     * Scope to get only recurring adjustments.
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('frequency', AdjustmentFrequency::Recurring);
    }

    /**
     * Scope to get adjustments applicable to a payroll period.
     */
    public function scopeApplicableForPeriod(Builder $query, PayrollPeriod $period): Builder
    {
        return $query->where('status', AdjustmentStatus::Active)
            ->where(function ($q) use ($period) {
                // One-time adjustments for this specific period
                $q->where(function ($oneTime) use ($period) {
                    $oneTime->where('frequency', AdjustmentFrequency::OneTime)
                        ->where('target_payroll_period_id', $period->id);
                })
                // Or recurring adjustments within their date range
                    ->orWhere(function ($recurring) use ($period) {
                        $recurring->where('frequency', AdjustmentFrequency::Recurring)
                            ->where('recurring_start_date', '<=', $period->cutoff_end)
                            ->where(function ($end) use ($period) {
                                $end->whereNull('recurring_end_date')
                                    ->orWhere('recurring_end_date', '>=', $period->cutoff_start);
                            });
                    });
            });
    }

    /**
     * Check if this adjustment is applicable for a payroll period.
     */
    public function isApplicableForPeriod(PayrollPeriod $period): bool
    {
        if (! $this->status->isApplicable()) {
            return false;
        }

        // For balance-tracking adjustments, check if balance remains
        if ($this->has_balance_tracking && (float) $this->remaining_balance <= 0) {
            return false;
        }

        // For one-time adjustments
        if ($this->frequency === AdjustmentFrequency::OneTime) {
            return $this->target_payroll_period_id === $period->id;
        }

        // For recurring adjustments
        if ($this->frequency === AdjustmentFrequency::Recurring) {
            // Check date range
            if ($this->recurring_start_date > $period->cutoff_end) {
                return false;
            }

            if ($this->recurring_end_date && $this->recurring_end_date < $period->cutoff_start) {
                return false;
            }

            // Check remaining occurrences
            if ($this->remaining_occurrences !== null && $this->remaining_occurrences <= 0) {
                return false;
            }

            // Check if already applied to this period
            if ($this->applications()->where('payroll_period_id', $period->id)->exists()) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Get the amount to apply for a payroll period.
     *
     * For balance-tracking adjustments, returns the lesser of
     * the configured amount and remaining balance.
     */
    public function getAmountForPeriod(): float
    {
        if ($this->has_balance_tracking) {
            return min((float) $this->amount, (float) $this->remaining_balance);
        }

        return (float) $this->amount;
    }

    /**
     * Record an application of this adjustment to a payroll period.
     */
    public function recordApplication(
        PayrollPeriod $period,
        ?PayrollEntry $entry,
        float $amount
    ): AdjustmentApplication {
        $balanceBefore = $this->has_balance_tracking ? (float) $this->remaining_balance : null;
        $balanceAfter = $this->has_balance_tracking ? max(0, $balanceBefore - $amount) : null;

        $application = $this->applications()->create([
            'payroll_period_id' => $period->id,
            'payroll_entry_id' => $entry?->id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'applied_at' => now(),
            'status' => 'applied',
        ]);

        // Update adjustment balances and counters
        $updates = [
            'total_applied' => (float) $this->total_applied + $amount,
        ];

        if ($this->has_balance_tracking) {
            $updates['remaining_balance'] = $balanceAfter;

            // Auto-complete if balance is zero
            if ($balanceAfter <= 0) {
                $updates['status'] = AdjustmentStatus::Completed;
            }
        }

        if ($this->remaining_occurrences !== null) {
            $updates['remaining_occurrences'] = max(0, $this->remaining_occurrences - 1);

            // Auto-complete if no occurrences remain
            if ($updates['remaining_occurrences'] <= 0) {
                $updates['status'] = AdjustmentStatus::Completed;
            }
        }

        $this->update($updates);

        return $application;
    }

    /**
     * Mark this adjustment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => AdjustmentStatus::Completed,
        ]);
    }

    /**
     * Put this adjustment on hold.
     */
    public function putOnHold(?string $notes = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['on_hold_at'] = now()->toDateTimeString();
        if ($notes) {
            $metadata['on_hold_reason'] = $notes;
        }

        $this->update([
            'status' => AdjustmentStatus::OnHold,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Resume this adjustment from on-hold status.
     */
    public function resume(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['resumed_at'] = now()->toDateTimeString();

        $this->update([
            'status' => AdjustmentStatus::Active,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Cancel this adjustment.
     */
    public function cancel(?string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancelled_at'] = now()->toDateTimeString();
        if ($reason) {
            $metadata['cancellation_reason'] = $reason;
        }

        $this->update([
            'status' => AdjustmentStatus::Cancelled,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get the payment progress as a percentage (for balance-tracking adjustments).
     */
    public function getProgressPercentage(): float
    {
        if (! $this->has_balance_tracking || ! $this->total_amount || (float) $this->total_amount <= 0) {
            return 0;
        }

        return min(100, ((float) $this->total_applied / (float) $this->total_amount) * 100);
    }

    /**
     * Check if this is an earning adjustment.
     */
    public function isEarning(): bool
    {
        return $this->adjustment_category === AdjustmentCategory::Earning;
    }

    /**
     * Check if this is a deduction adjustment.
     */
    public function isDeduction(): bool
    {
        return $this->adjustment_category === AdjustmentCategory::Deduction;
    }

    /**
     * Check if this is a one-time adjustment.
     */
    public function isOneTime(): bool
    {
        return $this->frequency === AdjustmentFrequency::OneTime;
    }

    /**
     * Check if this is a recurring adjustment.
     */
    public function isRecurring(): bool
    {
        return $this->frequency === AdjustmentFrequency::Recurring;
    }
}
