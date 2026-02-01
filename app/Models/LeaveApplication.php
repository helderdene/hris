<?php

namespace App\Models;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * LeaveApplication model for employee leave requests.
 *
 * Supports multi-level approval workflow with automatic balance tracking.
 */
class LeaveApplication extends TenantModel
{
    /** @use HasFactory<\Database\Factories\LeaveApplicationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_balance_id',
        'reference_number',
        'start_date',
        'end_date',
        'total_days',
        'is_half_day_start',
        'is_half_day_end',
        'reason',
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
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:2',
            'is_half_day_start' => 'boolean',
            'is_half_day_end' => 'boolean',
            'status' => LeaveApplicationStatus::class,
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

        static::creating(function (LeaveApplication $application) {
            if (empty($application->reference_number)) {
                $application->reference_number = self::generateReferenceNumber();
            }

            if ($application->status === null) {
                $application->status = LeaveApplicationStatus::Draft;
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
     * Get the leave type for this application.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the leave balance linked to this application.
     */
    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    /**
     * Get all approval records for this application.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveApplicationApproval::class)->orderBy('approval_level');
    }

    /**
     * Get the current pending approval.
     */
    public function currentApproval(): HasOne
    {
        return $this->hasOne(LeaveApplicationApproval::class)
            ->where('approval_level', $this->current_approval_level)
            ->where('decision', LeaveApprovalDecision::Pending);
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
        return $query->where('status', LeaveApplicationStatus::Pending);
    }

    /**
     * Scope to filter applications pending approval by a specific approver.
     */
    public function scopeForApprover(Builder $query, int|Employee $approver): Builder
    {
        $approverId = $approver instanceof Employee ? $approver->id : $approver;

        return $query->where('status', LeaveApplicationStatus::Pending)
            ->whereHas('approvals', function ($q) use ($approverId) {
                $q->where('approver_employee_id', $approverId)
                    ->where('decision', LeaveApprovalDecision::Pending)
                    ->whereColumn('approval_level', 'leave_applications.current_approval_level');
            });
    }

    /**
     * Scope to filter applications with overlapping dates for an employee.
     */
    public function scopeOverlapping(Builder $query, int $employeeId, string $startDate, string $endDate, ?int $excludeId = null): Builder
    {
        return $query->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveApplicationStatus::Pending, LeaveApplicationStatus::Approved])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));
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
     * Get the next approver in the chain.
     */
    protected function nextApprover(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->status !== LeaveApplicationStatus::Pending) {
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
     * Get the formatted date range.
     */
    protected function dateRange(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->start_date->equalTo($this->end_date)) {
                    return $this->start_date->format('M d, Y');
                }

                return $this->start_date->format('M d').' - '.$this->end_date->format('M d, Y');
            }
        );
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $year = now()->year;
        $prefix = 'LV-'.$year.'-';

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

    /**
     * Calculate the total days for a date range.
     */
    public static function calculateTotalDays(
        string $startDate,
        string $endDate,
        bool $isHalfDayStart = false,
        bool $isHalfDayEnd = false
    ): float {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        $days = $start->diffInDays($end) + 1;

        if ($isHalfDayStart) {
            $days -= 0.5;
        }

        if ($isHalfDayEnd) {
            $days -= 0.5;
        }

        return max(0.5, $days);
    }
}
