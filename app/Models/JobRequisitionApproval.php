<?php

namespace App\Models;

use App\Enums\LeaveApprovalDecision;
use App\Enums\PriorityLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JobRequisitionApproval model for tracking approval chain.
 *
 * Each level in the approval chain has its own record with approver snapshot
 * for audit trail purposes.
 */
class JobRequisitionApproval extends TenantModel
{
    /** @use HasFactory<\Database\Factories\JobRequisitionApprovalFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_requisition_id',
        'approval_level',
        'approver_type',
        'approver_employee_id',
        'approver_name',
        'approver_position',
        'decision',
        'remarks',
        'decided_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_level' => 'integer',
            'decision' => LeaveApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Get the job requisition this approval belongs to.
     */
    public function jobRequisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class);
    }

    /**
     * Get the employee who is the approver.
     */
    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    /**
     * Scope to filter approvals pending decision.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('decision', LeaveApprovalDecision::Pending);
    }

    /**
     * Scope to filter approvals for a specific approver.
     */
    public function scopeForApprover(Builder $query, int|Employee $approver): Builder
    {
        $approverId = $approver instanceof Employee ? $approver->id : $approver;

        return $query->where('approver_employee_id', $approverId);
    }

    /**
     * Check if this approval is pending.
     */
    protected function isPending(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decision === LeaveApprovalDecision::Pending
        );
    }

    /**
     * Check if the decision has been made.
     */
    protected function isDecided(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decision->isDecided()
        );
    }

    /**
     * Record an approval decision.
     */
    public function approve(?string $remarks = null): void
    {
        $this->decision = LeaveApprovalDecision::Approved;
        $this->remarks = $remarks;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Record a rejection decision.
     */
    public function reject(string $reason): void
    {
        $this->decision = LeaveApprovalDecision::Rejected;
        $this->remarks = $reason;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Mark this approval as skipped.
     */
    public function skip(?string $reason = null): void
    {
        $this->decision = LeaveApprovalDecision::Skipped;
        $this->remarks = $reason ?? 'Approval level skipped';
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Check if this approval is overdue (pending for more than 48 hours).
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_pending && $this->created_at->diffInHours(now()) > 48
        );
    }

    /**
     * Check if this approval is approaching deadline (pending for 24-48 hours).
     */
    protected function isApproachingDeadline(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_pending) {
                    return false;
                }
                $hours = $this->created_at->diffInHours(now());

                return $hours >= 24 && $hours <= 48;
            }
        );
    }

    /**
     * Get the priority level based on timing.
     */
    protected function priorityLevel(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_pending) {
                    return null;
                }
                if ($this->is_overdue) {
                    return PriorityLevel::Critical;
                }
                if ($this->is_approaching_deadline) {
                    return PriorityLevel::High;
                }

                return PriorityLevel::Medium;
            }
        );
    }

    /**
     * Get hours remaining until overdue (48-hour deadline).
     */
    protected function hoursRemaining(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_pending) {
                    return null;
                }
                $deadline = $this->created_at->addHours(48);

                return max(0, now()->diffInHours($deadline, false));
            }
        );
    }

    /**
     * Get hours overdue (negative hours remaining).
     */
    protected function hoursOverdue(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->is_pending || ! $this->is_overdue) {
                    return 0;
                }

                return $this->created_at->addHours(48)->diffInHours(now());
            }
        );
    }

    /**
     * Scope to filter approvals that are overdue.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()
            ->where('created_at', '<', Carbon::now()->subHours(48));
    }

    /**
     * Scope to filter approvals approaching deadline (24-48 hours old).
     */
    public function scopeApproaching(Builder $query): Builder
    {
        return $query->pending()
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->where('created_at', '>', Carbon::now()->subHours(48));
    }

    /**
     * Scope to filter approvals that need priority attention.
     */
    public function scopePriority(Builder $query): Builder
    {
        return $query->pending()
            ->where('created_at', '<', Carbon::now()->subHours(24));
    }
}
