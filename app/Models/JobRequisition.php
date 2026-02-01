<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Enums\LeaveApprovalDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * JobRequisition model for employee job requisition requests.
 *
 * Supports multi-level approval workflow for hiring requests.
 */
class JobRequisition extends TenantModel
{
    /** @use HasFactory<\Database\Factories\JobRequisitionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'department_id',
        'requested_by_employee_id',
        'reference_number',
        'headcount',
        'employment_type',
        'salary_range_min',
        'salary_range_max',
        'justification',
        'urgency',
        'preferred_start_date',
        'requirements',
        'remarks',
        'status',
        'current_approval_level',
        'total_approval_levels',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
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
            'headcount' => 'integer',
            'employment_type' => EmploymentType::class,
            'salary_range_min' => 'decimal:2',
            'salary_range_max' => 'decimal:2',
            'urgency' => JobRequisitionUrgency::class,
            'preferred_start_date' => 'date',
            'requirements' => 'array',
            'status' => JobRequisitionStatus::class,
            'current_approval_level' => 'integer',
            'total_approval_levels' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (JobRequisition $requisition) {
            if (empty($requisition->reference_number)) {
                $requisition->reference_number = self::generateReferenceNumber();
            }

            if ($requisition->status === null) {
                $requisition->status = JobRequisitionStatus::Draft;
            }
        });
    }

    /**
     * Get the position being requisitioned.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the department for this requisition.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the employee who requested this requisition.
     */
    public function requestedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    /**
     * Get job postings created from this requisition.
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Get all approval records for this requisition.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(JobRequisitionApproval::class)->orderBy('approval_level');
    }

    /**
     * Get the current pending approval.
     */
    public function currentApproval(): HasOne
    {
        return $this->hasOne(JobRequisitionApproval::class)
            ->where('approval_level', $this->current_approval_level)
            ->where('decision', LeaveApprovalDecision::Pending);
    }

    /**
     * Scope to filter requisitions for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('requested_by_employee_id', $employeeId);
    }

    /**
     * Scope to filter only pending requisitions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', JobRequisitionStatus::Pending);
    }

    /**
     * Scope to filter requisitions pending approval by a specific approver.
     */
    public function scopeForApprover(Builder $query, int|Employee $approver): Builder
    {
        $approverId = $approver instanceof Employee ? $approver->id : $approver;

        return $query->where('status', JobRequisitionStatus::Pending)
            ->whereHas('approvals', function ($q) use ($approverId) {
                $q->where('approver_employee_id', $approverId)
                    ->where('decision', LeaveApprovalDecision::Pending)
                    ->whereColumn('approval_level', 'job_requisitions.current_approval_level');
            });
    }

    /**
     * Check if the requisition can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the requisition can be cancelled.
     */
    protected function canBeCancelled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeCancelled()
        );
    }

    /**
     * Get the next approver in the chain.
     */
    protected function nextApprover(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->status !== JobRequisitionStatus::Pending) {
                    return null;
                }

                return $this->approvals()
                    ->where('decision', LeaveApprovalDecision::Pending)
                    ->orderBy('approval_level')
                    ->first();
            }
        );
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $year = now()->year;
        $prefix = 'JR-'.$year.'-';

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
