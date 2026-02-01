<?php

namespace App\Models;

use App\Enums\EarningType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PayrollEarning model for individual earning line items.
 *
 * Records detailed breakdown of each earning component (basic pay, OT, etc.)
 * for audit trail and payslip generation.
 */
class PayrollEarning extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PayrollEarningFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payroll_entry_id',
        'earning_type',
        'earning_code',
        'description',
        'quantity',
        'quantity_unit',
        'rate',
        'multiplier',
        'amount',
        'is_taxable',
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
            'earning_type' => EarningType::class,
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'multiplier' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    /**
     * Get the payroll entry this earning belongs to.
     */
    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }

    /**
     * Scope to filter by earning type.
     */
    public function scopeOfType(Builder $query, EarningType $type): Builder
    {
        return $query->where('earning_type', $type);
    }

    /**
     * Scope to get only taxable earnings.
     */
    public function scopeTaxable(Builder $query): Builder
    {
        return $query->where('is_taxable', true);
    }

    /**
     * Scope to get only non-taxable earnings.
     */
    public function scopeNonTaxable(Builder $query): Builder
    {
        return $query->where('is_taxable', false);
    }

    /**
     * Get the formatted quantity with unit.
     */
    public function getFormattedQuantityAttribute(): string
    {
        if ($this->quantity_unit) {
            return number_format($this->quantity, 2).' '.$this->quantity_unit;
        }

        return number_format($this->quantity, 2);
    }

    /**
     * Get the computation breakdown as a string.
     */
    public function getComputationBreakdownAttribute(): string
    {
        $parts = [];

        if ($this->quantity > 0) {
            $parts[] = number_format($this->quantity, 2).($this->quantity_unit ? " {$this->quantity_unit}" : '');
        }

        if ($this->rate > 0) {
            $parts[] = '@ '.number_format($this->rate, 2);
        }

        if ($this->multiplier != 1.00) {
            $parts[] = 'x '.number_format($this->multiplier, 2);
        }

        return implode(' ', $parts) ?: '-';
    }
}
