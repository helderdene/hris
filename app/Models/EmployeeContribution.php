<?php

namespace App\Models;

use App\Enums\ContributionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Employee Contribution model for storing per-employee contribution records.
 *
 * Each record represents a single government contribution (SSS, PhilHealth, or Pag-IBIG)
 * for a specific payroll period.
 */
class EmployeeContribution extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeContributionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'employee_contributions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'payroll_period_start',
        'payroll_period_end',
        'contribution_type',
        'basis_salary',
        'employee_share',
        'employer_share',
        'total_contribution',
        'sss_ec_contribution',
        'contribution_table_id',
        'contribution_table_type',
        'remarks',
        'calculated_at',
        'calculated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payroll_period_start' => 'date',
            'payroll_period_end' => 'date',
            'contribution_type' => ContributionType::class,
            'basis_salary' => 'decimal:2',
            'employee_share' => 'decimal:2',
            'employer_share' => 'decimal:2',
            'total_contribution' => 'decimal:2',
            'sss_ec_contribution' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this contribution belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the contribution table used for calculation.
     */
    public function contributionTable(): MorphTo
    {
        return $this->morphTo('contribution_table');
    }

    /**
     * Get the user who calculated this contribution.
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * Scope to filter by contribution type.
     */
    public function scopeOfType($query, ContributionType $type)
    {
        return $query->where('contribution_type', $type->value);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by payroll period.
     */
    public function scopeForPeriod($query, $periodStart, $periodEnd)
    {
        return $query->where('payroll_period_start', $periodStart)
            ->where('payroll_period_end', $periodEnd);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('payroll_period_start', $year);
    }

    /**
     * Get the contribution type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->contribution_type->label();
    }

    /**
     * Get the formatted period string.
     */
    public function getPeriodLabelAttribute(): string
    {
        return $this->payroll_period_start->format('M d').' - '.$this->payroll_period_end->format('M d, Y');
    }
}
