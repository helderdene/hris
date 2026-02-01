<?php

namespace App\Models;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loan application model for employee loan requests.
 *
 * Supports SSS, Pag-IBIG, and company loan applications with
 * single-level HR approval workflow.
 */
class LoanApplication extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LoanApplicationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'reference_number',
        'loan_type',
        'amount_requested',
        'term_months',
        'purpose',
        'documents',
        'status',
        'submitted_at',
        'reviewer_employee_id',
        'reviewer_remarks',
        'reviewed_at',
        'employee_loan_id',
        'cancellation_reason',
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
            'status' => LoanApplicationStatus::class,
            'amount_requested' => 'decimal:2',
            'term_months' => 'integer',
            'documents' => 'array',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LoanApplication $application) {
            if (empty($application->reference_number)) {
                $application->reference_number = self::generateReferenceNumber();
            }

            if ($application->status === null) {
                $application->status = LoanApplicationStatus::Draft;
            }
        });
    }

    /**
     * Get the employee who submitted this application.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the reviewer (HR employee) for this application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    /**
     * Get the employee loan created on approval.
     */
    public function employeeLoan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class);
    }

    /**
     * Get the user who created this application.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to filter applications for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter only pending applications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LoanApplicationStatus::Pending);
    }

    /**
     * Check if the application can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the application can be cancelled.
     */
    protected function canBeCancelled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeCancelled()
        );
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $year = now()->year;
        $prefix = 'LA-'.$year.'-';

        $lastNumber = self::query()
            ->where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
