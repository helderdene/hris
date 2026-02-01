<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pag-IBIG Contribution Table model for storing Pag-IBIG contribution rate schedules.
 *
 * Each table represents a set of contribution tiers effective from a specific date.
 * The latest effective_from date with is_active=true is considered current.
 */
class PagibigContributionTable extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PagibigContributionTableFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'pagibig_contribution_tables';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'effective_from',
        'description',
        'max_monthly_compensation',
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
            'max_monthly_compensation' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tiers for this contribution table.
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PagibigContributionTier::class)->orderBy('min_salary');
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
     * Find the tier for a given salary.
     */
    public function findTierForSalary(float $salary): ?PagibigContributionTier
    {
        return $this->tiers()
            ->where('min_salary', '<=', $salary)
            ->where(function ($query) use ($salary) {
                $query->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $salary);
            })
            ->first();
    }

    /**
     * Calculate the contribution for a given salary.
     *
     * @return array{total: float, employee_share: float, employer_share: float, basis_salary: float}
     */
    public function calculateContribution(float $salary): array
    {
        $tier = $this->findTierForSalary($salary);

        if (! $tier) {
            return [
                'total' => 0,
                'employee_share' => 0,
                'employer_share' => 0,
                'basis_salary' => 0,
            ];
        }

        // Pag-IBIG contribution is based on max monthly compensation (₱5,000 default)
        $basisSalary = min($salary, $this->max_monthly_compensation);

        $employeeShare = round($basisSalary * $tier->employee_rate, 2);
        $employerShare = round($basisSalary * $tier->employer_rate, 2);
        $totalContribution = $employeeShare + $employerShare;

        return [
            'total' => $totalContribution,
            'employee_share' => $employeeShare,
            'employer_share' => $employerShare,
            'basis_salary' => $basisSalary,
        ];
    }
}
