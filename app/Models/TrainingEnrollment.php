<?php

namespace App\Models;

use App\Enums\CompletionStatus;
use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Training enrollment model for tracking employee session enrollments.
 */
class TrainingEnrollment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\TrainingEnrollmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'training_session_id',
        'employee_id',
        'status',
        'enrolled_at',
        'attended_at',
        'assessment_score',
        'completion_status',
        'certificate_number',
        'certificate_issued_at',
        'notes',
        'enrolled_by',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'reference_number',
        'submitted_at',
        'request_reason',
        'approver_employee_id',
        'approver_name',
        'approver_position',
        'approver_remarks',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'completion_status' => CompletionStatus::class,
            'enrolled_at' => 'datetime',
            'attended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'certificate_issued_at' => 'date',
            'assessment_score' => 'decimal:2',
        ];
    }

    /**
     * Check if the training enrollment is completed (attended with completion status).
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === EnrollmentStatus::Attended
            && $this->completion_status === CompletionStatus::Completed;
    }

    /**
     * Check if a certificate has been issued.
     */
    public function getHasCertificateAttribute(): bool
    {
        return $this->certificate_number !== null && $this->certificate_issued_at !== null;
    }

    /**
     * Generate a unique reference number for enrollment requests.
     */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'TRN';
        $date = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        $reference = "{$prefix}-{$date}-{$random}";

        while (static::where('reference_number', $reference)->exists()) {
            $random = strtoupper(Str::random(4));
            $reference = "{$prefix}-{$date}-{$random}";
        }

        return $reference;
    }

    /**
     * Get the training session this enrollment belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * Get the enrolled employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the employee who enrolled this participant.
     */
    public function enrolledByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'enrolled_by');
    }

    /**
     * Get the employee who cancelled this enrollment.
     */
    public function cancelledByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }

    /**
     * Get the approver employee.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    /**
     * Scope to get only pending approval enrollments.
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Pending->value);
    }

    /**
     * Scope to get pending enrollments for a specific approver.
     */
    public function scopePendingForApprover(Builder $query, int|Employee $approver): Builder
    {
        $approverId = $approver instanceof Employee ? $approver->id : $approver;

        return $query
            ->where('status', EnrollmentStatus::Pending->value)
            ->where('approver_employee_id', $approverId);
    }

    /**
     * Scope to get only active (confirmed) enrollments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Confirmed->value);
    }

    /**
     * Scope to get enrollments for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to get upcoming enrollments (session hasn't started yet).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereHas('session', function ($q) {
            $q->where('start_date', '>=', now()->startOfDay());
        });
    }

    /**
     * Scope to get past enrollments (session has ended).
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->whereHas('session', function ($q) {
            $q->where('end_date', '<', now()->startOfDay());
        });
    }

    /**
     * Scope to get completed enrollments (attended with completed status).
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query
            ->where('status', EnrollmentStatus::Attended->value)
            ->where('completion_status', CompletionStatus::Completed->value);
    }

    /**
     * Scope to get enrollments with certificates.
     */
    public function scopeWithCertificate(Builder $query): Builder
    {
        return $query
            ->whereNotNull('certificate_number')
            ->whereNotNull('certificate_issued_at');
    }

    /**
     * Scope to filter by completion status.
     */
    public function scopeByCompletionStatus(Builder $query, CompletionStatus $status): Builder
    {
        return $query->where('completion_status', $status->value);
    }

    /**
     * Mark this enrollment as attended.
     */
    public function markAsAttended(): bool
    {
        if (! $this->status->canMarkAttendance()) {
            return false;
        }

        $this->status = EnrollmentStatus::Attended;
        $this->attended_at = now();

        return $this->save();
    }

    /**
     * Mark this enrollment as no-show.
     */
    public function markAsNoShow(): bool
    {
        if (! $this->status->canMarkAttendance()) {
            return false;
        }

        $this->status = EnrollmentStatus::NoShow;

        return $this->save();
    }

    /**
     * Cancel this enrollment.
     */
    public function cancel(?Employee $cancelledBy = null, ?string $reason = null): bool
    {
        if (! $this->status->canBeCancelled()) {
            return false;
        }

        $this->status = EnrollmentStatus::Cancelled;
        $this->cancelled_at = now();
        $this->cancelled_by = $cancelledBy?->id;
        $this->cancellation_reason = $reason;

        return $this->save();
    }

    /**
     * Check if the enrollment is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if the enrollment is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Check if this enrollment can be approved by the given employee.
     */
    public function canBeApprovedBy(Employee $employee): bool
    {
        return $this->status->canBeApproved()
            && $this->approver_employee_id === $employee->id;
    }

    /**
     * Check if this enrollment can be rejected by the given employee.
     */
    public function canBeRejectedBy(Employee $employee): bool
    {
        return $this->status->canBeRejected()
            && $this->approver_employee_id === $employee->id;
    }
}
