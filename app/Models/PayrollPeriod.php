<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PayrollPeriod model for managing individual payroll period instances.
 *
 * Each period represents an actual payroll run with specific cutoff date ranges,
 * pay date, status tracking, and financial totals.
 */
class PayrollPeriod extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PayrollPeriodFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payroll_cycle_id',
        'name',
        'period_type',
        'year',
        'period_number',
        'cutoff_start',
        'cutoff_end',
        'pay_date',
        'status',
        'employee_count',
        'total_gross',
        'total_deductions',
        'total_net',
        'opened_at',
        'closed_at',
        'closed_by',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_type' => PayrollPeriodType::class,
            'status' => PayrollPeriodStatus::class,
            'year' => 'integer',
            'period_number' => 'integer',
            'cutoff_start' => 'date',
            'cutoff_end' => 'date',
            'pay_date' => 'date',
            'employee_count' => 'integer',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the payroll cycle this period belongs to.
     */
    public function payrollCycle(): BelongsTo
    {
        return $this->belongsTo(PayrollCycle::class);
    }

    /**
     * Get the user who closed this period.
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the payroll entries for this period.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * Scope to filter periods by year.
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter periods by status.
     */
    public function scopeByStatus(Builder $query, PayrollPeriodStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get only open periods.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PayrollPeriodStatus::Open);
    }

    /**
     * Scope to get periods that contain a specific date.
     */
    public function scopeContainingDate(Builder $query, string $date): Builder
    {
        return $query->where('cutoff_start', '<=', $date)
            ->where('cutoff_end', '>=', $date);
    }

    /**
     * Scope to filter by payroll cycle.
     */
    public function scopeForCycle(Builder $query, int $cycleId): Builder
    {
        return $query->where('payroll_cycle_id', $cycleId);
    }

    /**
     * Scope to filter by period type.
     */
    public function scopeOfType(Builder $query, PayrollPeriodType $type): Builder
    {
        return $query->where('period_type', $type);
    }

    /**
     * Check if the period can transition to the given status.
     */
    public function canTransitionTo(PayrollPeriodStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Transition the period to a new status.
     *
     * @throws \InvalidArgumentException If the transition is not allowed
     */
    public function transitionTo(PayrollPeriodStatus $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$newStatus->value}"
            );
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === PayrollPeriodStatus::Open && $this->opened_at === null) {
            $updates['opened_at'] = now();
        }

        if ($newStatus === PayrollPeriodStatus::Closed) {
            $updates['closed_at'] = now();
            $updates['closed_by'] = auth()->id();
        }

        $this->update($updates);
    }

    /**
     * Check if this period can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if this period can be deleted.
     */
    public function isDeletable(): bool
    {
        return $this->status->isDeletable();
    }

    /**
     * Get a formatted name for this period.
     */
    public function getFormattedName(): string
    {
        $periodLabel = $this->period_type === PayrollPeriodType::Regular
            ? "Period {$this->period_number}"
            : $this->period_type->label();

        return "{$this->year} {$periodLabel}";
    }

    /**
     * Get the date range as a formatted string.
     */
    public function getDateRange(): string
    {
        return $this->cutoff_start->format('M j').' - '.$this->cutoff_end->format('M j, Y');
    }
}
