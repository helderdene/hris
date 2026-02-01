<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Withholding Tax Bracket model for storing individual tax brackets.
 *
 * Each bracket defines the tax computation for a compensation range within
 * a withholding tax table. Tax is calculated as:
 * base_tax + (excess_rate * (compensation - min_compensation))
 */
class WithholdingTaxBracket extends TenantModel
{
    /** @use HasFactory<\Database\Factories\WithholdingTaxBracketFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'withholding_tax_brackets';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'withholding_tax_table_id',
        'min_compensation',
        'max_compensation',
        'base_tax',
        'excess_rate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_compensation' => 'decimal:2',
            'max_compensation' => 'decimal:2',
            'base_tax' => 'decimal:2',
            'excess_rate' => 'decimal:4',
        ];
    }

    /**
     * Get the tax table this bracket belongs to.
     */
    public function taxTable(): BelongsTo
    {
        return $this->belongsTo(WithholdingTaxTable::class, 'withholding_tax_table_id');
    }

    /**
     * Check if this bracket applies to a given compensation amount.
     */
    public function appliesTo(float $compensation): bool
    {
        if ($compensation < $this->min_compensation) {
            return false;
        }

        if ($this->max_compensation === null) {
            return true;
        }

        return $compensation <= $this->max_compensation;
    }

    /**
     * Calculate the tax for a given compensation using this bracket.
     */
    public function calculateTax(float $compensation): float
    {
        if (! $this->appliesTo($compensation)) {
            return 0;
        }

        $excess = $compensation - $this->min_compensation;

        return (float) $this->base_tax + ($excess * (float) $this->excess_rate);
    }

    /**
     * Get a formatted compensation range string.
     */
    public function getCompensationRangeAttribute(): string
    {
        if ($this->max_compensation === null) {
            return number_format($this->min_compensation, 2).' and above';
        }

        return number_format($this->min_compensation, 2).' - '.number_format($this->max_compensation, 2);
    }
}
