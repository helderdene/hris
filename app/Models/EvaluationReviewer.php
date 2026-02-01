<?php

namespace App\Models;

use App\Enums\AssignmentMethod;
use App\Enums\EvaluationReviewerStatus;
use App\Enums\ReviewerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * EvaluationReviewer model for tracking who reviews whom in a 360-degree evaluation.
 *
 * Each reviewer is linked to a participant and can be of type: self, manager, peer, or direct_report.
 * Tracks the review workflow from invitation through submission or decline.
 */
class EvaluationReviewer extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'performance_cycle_participant_id',
        'reviewer_employee_id',
        'reviewer_type',
        'status',
        'assignment_method',
        'assigned_by',
        'invited_at',
        'started_at',
        'submitted_at',
        'declined_at',
        'decline_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewer_type' => ReviewerType::class,
            'status' => EvaluationReviewerStatus::class,
            'assignment_method' => AssignmentMethod::class,
            'invited_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /**
     * Get the participant being evaluated.
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class, 'performance_cycle_participant_id');
    }

    /**
     * Get the employee who is the reviewer.
     */
    public function reviewerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    /**
     * Get the user who assigned this reviewer.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the evaluation response for this reviewer.
     */
    public function evaluationResponse(): HasOne
    {
        return $this->hasOne(EvaluationResponse::class);
    }

    /**
     * Scope to filter by reviewer type.
     */
    public function scopeByType(Builder $query, ReviewerType|string $type): Builder
    {
        $value = $type instanceof ReviewerType ? $type->value : $type;

        return $query->where('reviewer_type', $value);
    }

    /**
     * Scope to filter pending reviewers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EvaluationReviewerStatus::Pending);
    }

    /**
     * Scope to filter in-progress reviewers.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', EvaluationReviewerStatus::InProgress);
    }

    /**
     * Scope to filter submitted reviewers.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', EvaluationReviewerStatus::Submitted);
    }

    /**
     * Scope to filter declined reviewers.
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', EvaluationReviewerStatus::Declined);
    }

    /**
     * Scope to filter reviewers for a specific participant.
     */
    public function scopeForParticipant(Builder $query, int $participantId): Builder
    {
        return $query->where('performance_cycle_participant_id', $participantId);
    }

    /**
     * Scope to filter reviewers by a specific employee (who is the reviewer).
     */
    public function scopeForReviewerEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('reviewer_employee_id', $employeeId);
    }

    /**
     * Mark the review as started.
     */
    public function start(): bool
    {
        if ($this->status !== EvaluationReviewerStatus::Pending) {
            return false;
        }

        return $this->update([
            'status' => EvaluationReviewerStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the review as submitted.
     */
    public function submit(): bool
    {
        if (! in_array($this->status, [EvaluationReviewerStatus::Pending, EvaluationReviewerStatus::InProgress])) {
            return false;
        }

        return $this->update([
            'status' => EvaluationReviewerStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark the review as declined.
     */
    public function decline(?string $reason = null): bool
    {
        if (! in_array($this->status, [EvaluationReviewerStatus::Pending, EvaluationReviewerStatus::InProgress])) {
            return false;
        }

        return $this->update([
            'status' => EvaluationReviewerStatus::Declined,
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
    }

    /**
     * Check if this reviewer type can view KPIs.
     */
    public function canViewKpis(): bool
    {
        return $this->reviewer_type->canViewKpis();
    }

    /**
     * Check if the reviewer can still edit their response.
     */
    public function canEdit(): bool
    {
        return $this->status->isEditable();
    }

    /**
     * Get or create the evaluation response for this reviewer.
     */
    public function getOrCreateResponse(): EvaluationResponse
    {
        return $this->evaluationResponse ?? $this->evaluationResponse()->create([
            'is_draft' => true,
            'last_saved_at' => now(),
        ]);
    }
}
