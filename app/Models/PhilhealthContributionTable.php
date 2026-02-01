<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PhilHealth Contribution Table model for storing PhilHealth contribution rates.
 *
 * PhilHealth uses a percentage-based calculation with floor and ceiling salary limits.
 * The total contribution is split equally between employee and employer by default.
 */
class PhilhealthContributionTable extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PhilhealthContributionTableFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'philhealth_contribution_tables';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'effective_from',
        'description',
        'contribution_rate',
        'employee_share_rate',
        'employer_share_rate',
        'salary_floor',
        'salary_ceiling',
        'min_contribution',
        'max_contribution',
        'is_active',
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
            'effective_from' => 'date',
            'contribution_rate' => 'decimal:4',
            'employee_share_rate' => 'decimal:4',
            'employer_share_rate' => 'decimal:4',
            'salary_floor' => 'decimal:2',
            'salary_ceiling' => 'decimal:2',
            'min_contribution' => 'decimal:2',
            'max_contribution' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who created this table.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active tables.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get tables effective on or before a given date.
     */
    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date);
    }

    /**
     * Get the current effective contribution table.
     */
    public static function current(): ?self
    {
        return static::active()
            ->effectiveOn(now())
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Get the contribution table effective on a specific date.
     */
    public static function effectiveAt($date): ?self
    {
        return static::active()
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Calculate the contribution for a given salary.
     *
     * @return array{total: float, employee_share: float, employer_share: float, basis_salary: float}
     */
    public function calculateContribution(float $salary): array
    {
        // Apply floor and ceiling
        $basisSalary = max($this->salary_floor, min($salary, $this->salary_ceiling));

        // Calculate total contribution
        $totalContribution = $basisSalary * $this->contribution_rate;

        // Apply min/max limits
        $totalContribution = max($this->min_contribution, min($totalContribution, $this->max_contribution));

        // Split between employee and employer
        $employeeShare = round($totalContribution * $this->employee_share_rate, 2);
        $employerShare = round($totalContribution * $this->employer_share_rate, 2);

        return [
            'total' => round($totalContribution, 2),
            'employee_share' => $employeeShare,
            'employer_share' => $employerShare,
            'basis_salary' => $basisSalary,
        ];
    }
}
