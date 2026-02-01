<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks each application of an adjustment to a payroll period.
 *
 * Records the amount applied, balance changes (for loan-type adjustments),
 * and links to the payroll entry where the adjustment was included.
 */
class AdjustmentApplication extends TenantModel
{
    /** @use HasFactory<\Database\Factories\AdjustmentApplicationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_adjustment_id',
        'payroll_period_id',
        'payroll_entry_id',
        'amount',
        'balance_before',
        'balance_after',
        'applied_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * Get the employee adjustment this application belongs to.
     */
    public function employeeAdjustment(): BelongsTo
    {
        return $this->belongsTo(EmployeeAdjustment::class);
    }

    /**
     * Get the payroll period this application was made in.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Get the payroll entry this application is linked to.
     */
    public function payrollEntry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class);
    }
}
