<?php

namespace App\Models;

use App\Enums\PayrollEntryStatus;
use App\Enums\PayType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PayrollEntry model for individual employee payroll records.
 *
 * Contains computed earnings, deductions, and net pay for an employee
 * within a specific payroll period. Includes employee snapshot data
 * for audit trail purposes.
 */
class PayrollEntry extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PayrollEntryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'employee_number',
        'employee_name',
        'department_name',
        'position_name',
        'basic_salary_snapshot',
        'pay_type_snapshot',
        'days_worked',
        'total_regular_minutes',
        'total_late_minutes',
        'total_undertime_minutes',
        'total_overtime_minutes',
        'total_night_diff_minutes',
        'absent_days',
        'holiday_days',
        'basic_pay',
        'overtime_pay',
        'night_diff_pay',
        'holiday_pay',
        'allowances_total',
        'bonuses_total',
        'gross_pay',
        'sss_employee',
        'sss_employer',
        'philhealth_employee',
        'philhealth_employer',
        'pagibig_employee',
        'pagibig_employer',
        'withholding_tax',
        'other_deductions_total',
        'total_deductions',
        'total_employer_contributions',
        'net_pay',
        'status',
        'computed_at',
        'computed_by',
        'approved_at',
        'approved_by',
        'remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollEntryStatus::class,
            'pay_type_snapshot' => PayType::class,
            'basic_salary_snapshot' => 'decimal:2',
            'days_worked' => 'decimal:2',
            'total_regular_minutes' => 'integer',
            'total_late_minutes' => 'integer',
            'total_undertime_minutes' => 'integer',
            'total_overtime_minutes' => 'integer',
            'total_night_diff_minutes' => 'integer',
            'absent_days' => 'decimal:2',
            'holiday_days' => 'decimal:2',
            'basic_pay' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'night_diff_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'allowances_total' => 'decimal:2',
            'bonuses_total' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'sss_employee' => 'decimal:2',
            'sss_employer' => 'decimal:2',
            'philhealth_employee' => 'decimal:2',
            'philhealth_employer' => 'decimal:2',
            'pagibig_employee' => 'decimal:2',
            'pagibig_employer' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'other_deductions_total' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_employer_contributions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'computed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the payroll period this entry belongs to.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Get the employee this entry is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who computed this entry.
     */
    public function computedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * Get the user who approved this entry.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the earning line items for this entry.
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollEarning::class)->orderBy('id');
    }

    /**
     * Get the deduction line items for this entry.
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class)->orderBy('id');
    }

    /**
     * Scope to filter entries by payroll period.
     */
    public function scopeForPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('payroll_period_id', $periodId);
    }

    /**
     * Scope to filter entries by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter entries by status.
     */
    public function scopeByStatus(Builder $query, PayrollEntryStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get only computed entries.
     */
    public function scopeComputed(Builder $query): Builder
    {
        return $query->where('status', '!=', PayrollEntryStatus::Draft);
    }

    /**
     * Scope to get entries pending approval.
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PayrollEntryStatus::Computed,
            PayrollEntryStatus::Reviewed,
        ]);
    }

    /**
     * Check if the entry can transition to the given status.
     */
    public function canTransitionTo(PayrollEntryStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Transition the entry to a new status.
     *
     * @throws \InvalidArgumentException If the transition is not allowed
     */
    public function transitionTo(PayrollEntryStatus $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->status->value} to {$newStatus->value}"
            );
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === PayrollEntryStatus::Approved) {
            $updates['approved_at'] = now();
            $updates['approved_by'] = auth()->id();
        }

        $this->update($updates);
    }

    /**
     * Check if this entry can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Check if this entry can be recomputed.
     */
    public function canRecompute(): bool
    {
        return $this->status->canRecompute();
    }

    /**
     * Get total government contributions (employee share only).
     */
    public function getTotalGovernmentContributionsAttribute(): float
    {
        return (float) $this->sss_employee
            + (float) $this->philhealth_employee
            + (float) $this->pagibig_employee;
    }

    /**
     * Get taxable income (gross pay minus pre-tax deductions).
     */
    public function getTaxableIncomeAttribute(): float
    {
        return (float) $this->gross_pay - $this->total_government_contributions;
    }
}
