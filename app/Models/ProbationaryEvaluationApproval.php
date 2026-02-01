<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use App\Enums\ProbationaryApprovalDecision;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProbationaryEvaluationApproval model for tracking HR approval chain.
 *
 * Each level in the approval chain has its own record with approver snapshot
 * for audit trail purposes. Follows the LeaveApplicationApproval pattern.
 */
class ProbationaryEvaluationApproval extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ProbationaryEvaluationApprovalFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'probationary_evaluation_id',
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
            'decision' => ProbationaryApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Get the probationary evaluation this approval belongs to.
     */
    public function probationaryEvaluation(): BelongsTo
    {
        return $this->belongsTo(ProbationaryEvaluation::class);
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
        return $query->where('decision', ProbationaryApprovalDecision::Pending);
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
    public function isPending(): bool
    {
        return $this->decision === ProbationaryApprovalDecision::Pending;
    }

    /**
     * Check if the decision has been made.
     */
    public function isDecided(): bool
    {
        return $this->decision->isDecided();
    }

    /**
     * Check if the approval was approved.
     */
    public function isApproved(): bool
    {
        return $this->decision === ProbationaryApprovalDecision::Approved;
    }

    /**
     * Check if the approval was rejected.
     */
    public function isRejected(): bool
    {
        return $this->decision === ProbationaryApprovalDecision::Rejected;
    }

    /**
     * Check if revision was requested.
     */
    public function isRevisionRequested(): bool
    {
        return $this->decision === ProbationaryApprovalDecision::RevisionRequested;
    }

    /**
     * Record an approval decision.
     */
    public function approve(Employee $approver, ?string $remarks = null): void
    {
        $this->approver_employee_id = $approver->id;
        $this->approver_name = $approver->full_name;
        $this->approver_position = $approver->position?->name;
        $this->decision = ProbationaryApprovalDecision::Approved;
        $this->remarks = $remarks;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Record a rejection decision.
     */
    public function reject(Employee $approver, string $reason): void
    {
        $this->approver_employee_id = $approver->id;
        $this->approver_name = $approver->full_name;
        $this->approver_position = $approver->position?->name;
        $this->decision = ProbationaryApprovalDecision::Rejected;
        $this->remarks = $reason;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Request revision from the manager.
     */
    public function requestRevision(Employee $approver, string $reason): void
    {
        $this->approver_employee_id = $approver->id;
        $this->approver_name = $approver->full_name;
        $this->approver_position = $approver->position?->name;
        $this->decision = ProbationaryApprovalDecision::RevisionRequested;
        $this->remarks = $reason;
        $this->decided_at = now();
        $this->save();
    }

    /**
     * Check if this approval is overdue (pending for more than 48 hours).
     */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->created_at->diffInHours(now()) > 48;
    }

    /**
     * Check if this approval is approaching deadline (pending for 24-48 hours).
     */
    public function isApproachingDeadline(): bool
    {
        if (! $this->isPending()) {
            return false;
        }
        $hours = $this->created_at->diffInHours(now());

        return $hours >= 24 && $hours <= 48;
    }

    /**
     * Get the priority level based on timing.
     */
    public function getPriorityLevel(): ?PriorityLevel
    {
        if (! $this->isPending()) {
            return null;
        }
        if ($this->isOverdue()) {
            return PriorityLevel::Critical;
        }
        if ($this->isApproachingDeadline()) {
            return PriorityLevel::High;
        }

        return PriorityLevel::Medium;
    }

    /**
     * Get hours remaining until overdue (48-hour deadline).
     */
    public function getHoursRemaining(): ?int
    {
        if (! $this->isPending()) {
            return null;
        }
        $deadline = $this->created_at->addHours(48);

        return max(0, now()->diffInHours($deadline, false));
    }

    /**
     * Get hours overdue (negative hours remaining).
     */
    public function getHoursOverdue(): int
    {
        if (! $this->isPending() || ! $this->isOverdue()) {
            return 0;
        }

        return $this->created_at->addHours(48)->diffInHours(now());
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
