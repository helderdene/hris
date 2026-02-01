<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee loan model for tracking loans and their repayment.
 *
 * Supports government loans (SSS, Pag-IBIG) and company loans
 * with automatic payroll deduction integration.
 */
class EmployeeLoan extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeLoanFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'loan_type',
        'loan_code',
        'reference_number',
        'principal_amount',
        'interest_rate',
        'monthly_deduction',
        'term_months',
        'total_amount',
        'total_paid',
        'remaining_balance',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'status',
        'notes',
        'metadata',
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
            'loan_type' => LoanType::class,
            'status' => LoanStatus::class,
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'monthly_deduction' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the employee that owns this loan.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the payments for this loan.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Get the loan application that created this loan.
     */
    public function loanApplication(): HasOne
    {
        return $this->hasOne(LoanApplication::class);
    }

    /**
     * Get the user who created this loan.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active loans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active);
    }

    /**
     * Scope to get loans that should be deducted from payroll.
     */
    public function scopeDeductible(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Active)
            ->where('remaining_balance', '>', 0);
    }

    /**
     * Scope to filter loans for a specific employee.
     */
    public function scopeForEmployee(Builder $query, Employee|int $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by loan type.
     */
    public function scopeOfType(Builder $query, LoanType|string $type): Builder
    {
        $typeValue = $type instanceof LoanType ? $type->value : $type;

        return $query->where('loan_type', $typeValue);
    }

    /**
     * Scope to get only government loans (SSS, Pag-IBIG).
     */
    public function scopeGovernmentLoans(Builder $query): Builder
    {
        $governmentTypes = array_map(
            fn (LoanType $type) => $type->value,
            LoanType::governmentLoans()
        );

        return $query->whereIn('loan_type', $governmentTypes);
    }

    /**
     * Scope to get only company loans.
     */
    public function scopeCompanyLoans(Builder $query): Builder
    {
        $companyTypes = array_map(
            fn (LoanType $type) => $type->value,
            LoanType::companyLoans()
        );

        return $query->whereIn('loan_type', $companyTypes);
    }

    /**
     * Record a payment against this loan.
     */
    public function recordPayment(
        float $amount,
        string $paymentDate,
        string $paymentSource = 'payroll',
        ?int $payrollDeductionId = null,
        ?string $notes = null
    ): LoanPayment {
        $balanceBefore = (float) $this->remaining_balance;
        $balanceAfter = max(0, $balanceBefore - $amount);

        $payment = $this->payments()->create([
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'payment_date' => $paymentDate,
            'payment_source' => $paymentSource,
            'payroll_deduction_id' => $payrollDeductionId,
            'notes' => $notes,
        ]);

        $this->update([
            'total_paid' => (float) $this->total_paid + $amount,
            'remaining_balance' => $balanceAfter,
        ]);

        if ($balanceAfter <= 0) {
            $this->markAsCompleted();
        }

        return $payment;
    }

    /**
     * Get the deduction amount for payroll.
     *
     * Returns the lesser of monthly deduction or remaining balance.
     */
    public function getDeductionAmount(): float
    {
        return min((float) $this->monthly_deduction, (float) $this->remaining_balance);
    }

    /**
     * Mark this loan as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => LoanStatus::Completed,
            'actual_end_date' => now()->toDateString(),
        ]);
    }

    /**
     * Put this loan on hold.
     */
    public function putOnHold(?string $notes = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['on_hold_at'] = now()->toDateTimeString();
        if ($notes) {
            $metadata['on_hold_reason'] = $notes;
        }

        $this->update([
            'status' => LoanStatus::OnHold,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Resume this loan from on-hold status.
     */
    public function resume(): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['resumed_at'] = now()->toDateTimeString();

        $this->update([
            'status' => LoanStatus::Active,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Cancel this loan.
     */
    public function cancel(?string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancelled_at'] = now()->toDateTimeString();
        if ($reason) {
            $metadata['cancellation_reason'] = $reason;
        }

        $this->update([
            'status' => LoanStatus::Cancelled,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Calculate the payment progress as a percentage.
     */
    public function getProgressPercentage(): float
    {
        $totalAmount = (float) $this->total_amount;
        if ($totalAmount <= 0) {
            return 0;
        }

        return min(100, ((float) $this->total_paid / $totalAmount) * 100);
    }

    /**
     * Check if this loan is a government loan.
     */
    public function isGovernmentLoan(): bool
    {
        return $this->loan_type->isGovernmentLoan();
    }

    /**
     * Check if this loan is a company loan.
     */
    public function isCompanyLoan(): bool
    {
        return $this->loan_type->isCompanyLoan();
    }
}
