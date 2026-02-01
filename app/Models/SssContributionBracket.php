<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SSS Contribution Bracket model for storing individual contribution brackets.
 *
 * Each bracket defines the contribution amounts for a salary range within
 * an SSS contribution table.
 */
class SssContributionBracket extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SssContributionBracketFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'sss_contribution_brackets';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sss_contribution_table_id',
        'min_salary',
        'max_salary',
        'monthly_salary_credit',
        'employee_contribution',
        'employer_contribution',
        'total_contribution',
        'ec_contribution',
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
            'monthly_salary_credit' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'total_contribution' => 'decimal:2',
            'ec_contribution' => 'decimal:2',
        ];
    }

    /**
     * Get the contribution table this bracket belongs to.
     */
    public function contributionTable(): BelongsTo
    {
        return $this->belongsTo(SssContributionTable::class, 'sss_contribution_table_id');
    }

    /**
     * Check if this bracket applies to a given salary.
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
}
