<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SSS Contribution Table model for storing SSS contribution rate schedules.
 *
 * Each table represents a set of contribution brackets effective from a specific date.
 * The latest effective_from date with is_active=true is considered current.
 */
class SssContributionTable extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SssContributionTableFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'sss_contribution_tables';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'effective_from',
        'description',
        'employee_rate',
        'employer_rate',
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
            'employee_rate' => 'decimal:4',
            'employer_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the brackets for this contribution table.
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(SssContributionBracket::class)->orderBy('min_salary');
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
     * Find the bracket for a given salary.
     */
    public function findBracketForSalary(float $salary): ?SssContributionBracket
    {
        return $this->brackets()
            ->where('min_salary', '<=', $salary)
            ->where(function ($query) use ($salary) {
                $query->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $salary);
            })
            ->first();
    }
}
