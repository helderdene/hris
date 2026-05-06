<?php

namespace App\Models;

use App\Enums\LeaveApprovalDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LoanApplicationApproval model for tracking the loan approval chain.
 *
 * Each level (CFO → Admin Manager → Releasing) has its own record with an
 * approver snapshot for audit trail and a per-level deadline_at used by the
 * daily overdue-reminder command.
 *
 * Reuses LeaveApprovalDecision since the decision shape is identical
 * (pending/approved/rejected/skipped).
 */
class LoanApplicationApproval extends TenantModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'loan_application_id',
        'approval_level',
        'approver_type',
        'approver_employee_id',
        'approver_name',
        'approver_position',
        'decision',
        'remarks',
        'decided_at',
        'deadline_at',
        'last_reminder_sent_at',
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
            'deadline_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the loan application this approval belongs to.
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
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
     * Scope to filter approvals past their deadline (and still pending).
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now());
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
     * Check if this approval is past its deadline (and still pending).
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decision === LeaveApprovalDecision::Pending
                && $this->deadline_at
                && $this->deadline_at->isPast()
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
    public function reject(string $remarks): void
    {
        $this->decision = LeaveApprovalDecision::Rejected;
        $this->remarks = $remarks;
        $this->decided_at = now();
        $this->save();
    }
}
