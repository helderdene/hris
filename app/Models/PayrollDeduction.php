<?php

namespace App\Models;

use App\Enums\DeductionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PayrollDeduction model for individual deduction line items.
 *
 * Records detailed breakdown of each deduction component (SSS, PhilHealth, etc.)
 * for audit trail and payslip generation. Tracks both employee and employer shares.
 */
class PayrollDeduction extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PayrollDeductionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payroll_entry_id',
        'deduction_type',
        'deduction_code',
        'description',
        'basis_amount',
        'rate',
        'amount',
        'is_employee_share',
        'is_employer_share',
        'remarks',
        'contribution_table_type',
        'contribution_table_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deduction_type' => DeductionType::class,
            'basis_amount' => 'decimal:2',
            'rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'is_employee_share' => 'boolean',
            'is_employer_share' => 'boolean',
        ];
    }

    /**
     * Get the payroll entry this deduction belongs to.
     */
    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    /**
     * Scope to filter by deduction type.
     */
    public function scopeOfType(Builder $query, DeductionType $type): Builder
    {
        return $query->where('deduction_type', $type);
    }

    /**
     * Scope to get only employee share deductions.
     */
    public function scopeEmployeeShare(Builder $query): Builder
    {
        return $query->where('is_employee_share', true);
    }

    /**
     * Scope to get only employer share deductions.
     */
    public function scopeEmployerShare(Builder $query): Builder
    {
        return $query->where('is_employer_share', true);
    }

    /**
     * Scope to get government contribution deductions.
     */
    public function scopeGovernmentContributions(Builder $query): Builder
    {
        return $query->whereIn('deduction_type', [
            DeductionType::Sss,
            DeductionType::Philhealth,
            DeductionType::Pagibig,
        ]);
    }

    /**
     * Check if this deduction is a government contribution.
     */
    public function isGovernmentContribution(): bool
    {
        return $this->deduction_type->isGovernmentContribution();
    }

    /**
     * Get the computation breakdown as a string.
     */
    public function getComputationBreakdownAttribute(): string
    {
        $parts = [];

        if ($this->basis_amount > 0) {
            $parts[] = 'Base: '.number_format($this->basis_amount, 2);
        }

        if ($this->rate > 0) {
            $parts[] = 'Rate: '.number_format($this->rate * 100, 2).'%';
        }

        return implode(' | ', $parts) ?: '-';
    }

    /**
     * Get the share type label.
     */
    public function getShareTypeLabelAttribute(): string
    {
        if ($this->is_employee_share && $this->is_employer_share) {
            return 'Employee & Employer';
        }

        if ($this->is_employee_share) {
            return 'Employee';
        }

        if ($this->is_employer_share) {
            return 'Employer';
        }

        return '-';
    }
}
