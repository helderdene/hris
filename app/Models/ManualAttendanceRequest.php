<?php

namespace App\Models;

use App\Enums\ManualAttendanceRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ManualAttendanceRequest model for employee-submitted attendance corrections.
 *
 * An employee files a request for missing clock-in and/or clock-out punches
 * on a single date. Either the employee's Department Head or any HR Admin
 * Manager can approve. On approval, the service materializes AttendanceLog
 * rows with source=Manual and triggers DTR recalculation.
 */
class ManualAttendanceRequest extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ManualAttendanceRequestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference_number',
        'employee_id',
        'attendance_date',
        'time_in',
        'time_out',
        'reason',
        'status',
        'submitted_at',
        'decided_at',
        'cancelled_at',
        'decided_by_user_id',
        'decided_by_role',
        'decision_remarks',
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
            'attendance_date' => 'date',
            'status' => ManualAttendanceRequestStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
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

        static::creating(function (ManualAttendanceRequest $request) {
            if (empty($request->reference_number)) {
                $request->reference_number = self::generateReferenceNumber();
            }

            if ($request->status === null) {
                $request->status = ManualAttendanceRequestStatus::Draft;
            }
        });
    }

    /**
     * Get the employee who submitted this request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who decided this request (approved or rejected).
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * Scope to filter requests for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter only pending requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ManualAttendanceRequestStatus::Pending);
    }

    /**
     * Scope to filter only approved requests.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ManualAttendanceRequestStatus::Approved);
    }

    /**
     * Scope to filter pending requests the given employee is eligible to decide.
     *
     * Eligible approvers: the requesting employee's Department Head, OR any
     * employee with is_leave_admin_manager = true. Excludes the requester's
     * own requests.
     */
    public function scopeForApprover(Builder $query, Employee $approver): Builder
    {
        $query->where('status', ManualAttendanceRequestStatus::Pending)
            ->where('employee_id', '!=', $approver->id);

        if ($approver->is_leave_admin_manager) {
            return $query;
        }

        return $query->whereHas('employee.department', function (Builder $dept) use ($approver) {
            $dept->where('department_head_id', $approver->id);
        });
    }

    /**
     * Determine if the given user is eligible to decide this request.
     *
     * Eligibility rules (single-level, either-or):
     * - Request must be Pending.
     * - User's linked employee must be the requester's department head OR an
     *   active HR admin manager.
     * - The decider cannot be the requester.
     */
    public function canBeDecidedBy(User $user): bool
    {
        if ($this->status !== ManualAttendanceRequestStatus::Pending) {
            return false;
        }

        $deciderEmployee = Employee::query()->where('user_id', $user->id)->first();

        if (! $deciderEmployee) {
            return false;
        }

        if ($deciderEmployee->id === $this->employee_id) {
            return false;
        }

        if ($deciderEmployee->is_leave_admin_manager) {
            return true;
        }

        $departmentHeadId = $this->employee?->department?->department_head_id;

        return $departmentHeadId !== null && $departmentHeadId === $deciderEmployee->id;
    }

    /**
     * Resolve the decider's role label (department_head or admin_manager).
     *
     * Department Head is preferred when the decider is both the department
     * head and an admin manager, to preserve organisational hierarchy in the
     * audit trail.
     */
    public function resolveDeciderRole(Employee $decider): string
    {
        $departmentHeadId = $this->employee?->department?->department_head_id;

        if ($departmentHeadId !== null && $departmentHeadId === $decider->id) {
            return 'department_head';
        }

        return 'admin_manager';
    }

    /**
     * Check if the request can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the request can be cancelled.
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
        $prefix = 'MAR-'.$year.'-';

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
