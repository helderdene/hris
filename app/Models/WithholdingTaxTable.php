<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Withholding Tax Table model for storing BIR tax rate schedules.
 *
 * Each table represents a set of tax brackets effective from a specific date
 * for a specific pay period (daily, weekly, semi-monthly, monthly).
 * The latest effective_from date with is_active=true is considered current.
 */
class WithholdingTaxTable extends TenantModel
{
    /** @use HasFactory<\Database\Factories\WithholdingTaxTableFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Valid pay period options.
     */
    public const PAY_PERIODS = [
        'daily',
        'weekly',
        'semi_monthly',
        'monthly',
    ];

    /**
     * The table associated with the model.
     */
    protected $table = 'withholding_tax_tables';

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'effective_from_formatted',
        'pay_period_label',
        'brackets_count',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pay_period',
        'effective_from',
        'description',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the formatted effective date.
     */
    protected function effectiveFromFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->effective_from?->format('F j, Y'),
        );
    }

    /**
     * Get the human-readable pay period label.
     */
    protected function payPeriodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->pay_period) {
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'semi_monthly' => 'Semi-Monthly',
                'monthly' => 'Monthly',
                default => ucfirst($this->pay_period ?? ''),
            },
        );
    }

    /**
     * Get the number of brackets.
     */
    protected function bracketsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->brackets()->count(),
        );
    }

    /**
     * Get the brackets for this tax table.
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(WithholdingTaxBracket::class)->orderBy('min_compensation');
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
     * Scope to filter by pay period.
     */
    public function scopeForPayPeriod($query, string $payPeriod)
    {
        return $query->where('pay_period', $payPeriod);
    }

    /**
     * Get the current effective tax table for a pay period.
     */
    public static function current(string $payPeriod): ?self
    {
        return static::active()
            ->forPayPeriod($payPeriod)
            ->effectiveOn(now())
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Get the tax table effective on a specific date for a pay period.
     */
    public static function effectiveAt($date, string $payPeriod): ?self
    {
        return static::active()
            ->forPayPeriod($payPeriod)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Find the bracket for a given compensation amount.
     */
    public function findBracketForCompensation(float $compensation): ?WithholdingTaxBracket
    {
        return $this->brackets()
            ->where('min_compensation', '<=', $compensation)
            ->where(function ($query) use ($compensation) {
                $query->whereNull('max_compensation')
                    ->orWhere('max_compensation', '>=', $compensation);
            })
            ->first();
    }

    /**
     * Calculate the withholding tax for a given compensation.
     */
    public function calculateTax(float $compensation): float
    {
        $bracket = $this->findBracketForCompensation($compensation);

        if (! $bracket) {
            return 0;
        }

        return $bracket->calculateTax($compensation);
    }
}
