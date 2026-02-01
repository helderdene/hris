<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loan payment model for tracking individual payments against loans.
 *
 * Each payment records the amount, date, and balance changes for audit trail.
 */
class LoanPayment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LoanPaymentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_loan_id',
        'payroll_deduction_id',
        'amount',
        'balance_before',
        'balance_after',
        'payment_date',
        'payment_source',
        'notes',
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
            'payment_date' => 'date',
        ];
    }

    /**
     * Get the loan this payment belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    /**
     * Get the payroll deduction that generated this payment.
     */
    public function payrollDeduction(): BelongsTo
    {
        return $this->belongsTo(PayrollDeduction::class);
    }

    /**
     * Check if this payment was from payroll.
     */
    public function isFromPayroll(): bool
    {
        return $this->payment_source === 'payroll';
    }

    /**
     * Check if this payment was manual.
     */
    public function isManual(): bool
    {
        return $this->payment_source === 'manual';
    }
}
