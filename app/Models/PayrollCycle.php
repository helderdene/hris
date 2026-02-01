<?php

namespace App\Models;

use App\Enums\PayrollCycleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PayrollCycle model for defining payroll cycle configurations.
 *
 * Each cycle represents a recurring payroll pattern (e.g., semi-monthly, monthly)
 * with configurable cutoff rules that determine period date ranges.
 */
class PayrollCycle extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PayrollCycleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'cycle_type',
        'description',
        'status',
        'cutoff_rules',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle_type' => PayrollCycleType::class,
            'cutoff_rules' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the payroll periods that belong to this cycle.
     */
    public function payrollPeriods(): HasMany
    {
        return $this->hasMany(PayrollPeriod::class);
    }

    /**
     * Scope to filter only active cycles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get the default cycle.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by cycle type.
     */
    public function scopeOfType(Builder $query, PayrollCycleType $type): Builder
    {
        return $query->where('cycle_type', $type);
    }

    /**
     * Get the default cutoff rules for a cycle type.
     *
     * @return array<string, mixed>
     */
    public static function getDefaultCutoffRules(PayrollCycleType $type): array
    {
        return match ($type) {
            PayrollCycleType::SemiMonthly => [
                'first_half' => [
                    'start_day' => 1,
                    'end_day' => 15,
                    'pay_day' => 25,
                    'pay_day_adjustment' => 'before', // If pay day falls on weekend/holiday
                ],
                'second_half' => [
                    'start_day' => 16,
                    'end_day' => 'last', // End of month
                    'pay_day' => 10,
                    'pay_day_month_offset' => 1, // Next month
                    'pay_day_adjustment' => 'before',
                ],
            ],
            PayrollCycleType::Monthly => [
                'start_day' => 1,
                'end_day' => 'last',
                'pay_day' => 30,
                'pay_day_adjustment' => 'before',
            ],
            default => [],
        };
    }

    /**
     * Set this cycle as the default, unsetting any other default.
     */
    public function setAsDefault(): void
    {
        static::query()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Check if this cycle generates recurring periods.
     */
    public function isRecurring(): bool
    {
        return $this->cycle_type->isRecurring();
    }

    /**
     * Get the expected number of periods per year for this cycle.
     */
    public function getPeriodsPerYear(): ?int
    {
        return $this->cycle_type->periodsPerYear();
    }
}
