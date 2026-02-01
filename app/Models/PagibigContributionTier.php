<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pag-IBIG Contribution Tier model for storing individual contribution tiers.
 *
 * Each tier defines the contribution rates for a salary range within
 * a Pag-IBIG contribution table.
 */
class PagibigContributionTier extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PagibigContributionTierFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'pagibig_contribution_tiers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pagibig_contribution_table_id',
        'min_salary',
        'max_salary',
        'employee_rate',
        'employer_rate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
        ];
    }

    /**
     * Get the contribution table this tier belongs to.
     */
    public function contributionTable(): BelongsTo
    {
        return $this->belongsTo(PagibigContributionTable::class, 'pagibig_contribution_table_id');
    }

    /**
     * Check if this tier applies to a given salary.
     */
    public function appliesTo(float $salary): bool
    {
        if ($salary < $this->min_salary) {
            return false;
        }

        if ($this->max_salary === null) {
            return true;
        }

        return $salary <= $this->max_salary;
    }

    /**
     * Get a formatted salary range string.
     */
    public function getSalaryRangeAttribute(): string
    {
        if ($this->max_salary === null) {
            return number_format($this->min_salary, 2).' and above';
        }

        return number_format($this->min_salary, 2).' - '.number_format($this->max_salary, 2);
    }

    /**
     * Get a formatted rates string.
     */
    public function getRatesLabelAttribute(): string
    {
        $employeePercent = $this->employee_rate * 100;
        $employerPercent = $this->employer_rate * 100;

        return "{$employeePercent}% / {$employerPercent}%";
    }
}
