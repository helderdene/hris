<?php

namespace App\Models;

use App\Enums\ProbationaryApprovalDecision;
use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\ProbationaryMilestone;
use App\Enums\RegularizationRecommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ProbationaryEvaluation model for tracking employee evaluations
 * at 3rd and 5th month milestones.
 *
 * Links employees with their evaluating managers and tracks the
 * approval workflow through HR.
 */
class ProbationaryEvaluation extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ProbationaryEvaluationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'performance_cycle_participant_id',
        'evaluator_id',
        'evaluator_name',
        'evaluator_position',
        'milestone',
        'milestone_date',
        'due_date',
        'previous_evaluation_id',
        'status',
        'criteria_ratings',
        'overall_rating',
        'strengths',
        'areas_for_improvement',
        'manager_comments',
        'recommendation',
        'recommendation_conditions',
        'extension_months',
        'recommendation_reason',
        'submitted_at',
        'approved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milestone' => ProbationaryMilestone::class,
            'milestone_date' => 'date',
            'due_date' => 'date',
            'status' => ProbationaryEvaluationStatus::class,
            'criteria_ratings' => 'array',
            'overall_rating' => 'decimal:2',
            'recommendation' => RegularizationRecommendation::class,
            'extension_months' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the employee being evaluated.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the manager evaluating the employee.
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_id');
    }

    /**
     * Get the performance cycle participant if linked.
     */
    public function performanceCycleParticipant(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycleParticipant::class);
    }

    /**
     * Get the previous evaluation (for 5th month showing 3rd month results).
     */
    public function previousEvaluation(): BelongsTo
    {
        return $this->belongsTo(ProbationaryEvaluation::class, 'previous_evaluation_id');
    }

    /**
     * Get the next evaluation (3rd month linking to 5th month).
     */
    public function nextEvaluation(): HasOne
    {
        return $this->hasOne(ProbationaryEvaluation::class, 'previous_evaluation_id');
    }

    /**
     * Get the approvals for this evaluation.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ProbationaryEvaluationApproval::class);
    }

    /**
     * Get the current pending approval.
     */
    public function currentApproval(): HasOne
    {
        return $this->hasOne(ProbationaryEvaluationApproval::class)
            ->where('decision', ProbationaryApprovalDecision::Pending);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, ProbationaryEvaluationStatus|string $status): Builder
    {
        $value = $status instanceof ProbationaryEvaluationStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to filter by milestone.
     */
    public function scopeByMilestone(Builder $query, ProbationaryMilestone|string $milestone): Builder
    {
        $value = $milestone instanceof ProbationaryMilestone ? $milestone->value : $milestone;

        return $query->where('milestone', $value);
    }

    /**
     * Scope to filter evaluations for a specific evaluator (manager).
     */
    public function scopeForEvaluator(Builder $query, int|Employee $evaluator): Builder
    {
        $evaluatorId = $evaluator instanceof Employee ? $evaluator->id : $evaluator;

        return $query->where('evaluator_id', $evaluatorId);
    }

    /**
     * Scope to filter pending evaluations that need manager action.
     */
    public function scopeAwaitingManager(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProbationaryEvaluationStatus::Pending,
            ProbationaryEvaluationStatus::Draft,
            ProbationaryEvaluationStatus::RevisionRequested,
        ]);
    }

    /**
     * Scope to filter evaluations awaiting HR review.
     */
    public function scopeAwaitingHr(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProbationaryEvaluationStatus::Submitted,
            ProbationaryEvaluationStatus::HrReview,
        ]);
    }

    /**
     * Scope to filter overdue evaluations.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [
                ProbationaryEvaluationStatus::Approved,
                ProbationaryEvaluationStatus::Rejected,
            ]);
    }

    /**
     * Check if the evaluation can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the evaluation is overdue.
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->due_date->isPast() && ! $this->status->isFinal()
        );
    }

    /**
     * Check if this is a final evaluation (5th month).
     */
    protected function isFinalEvaluation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->milestone->isFinalEvaluation()
        );
    }

    /**
     * Check if recommendation is required (only for final evaluation).
     */
    protected function requiresRecommendation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->milestone->isFinalEvaluation()
        );
    }

    /**
     * Start the evaluation (change from pending to draft).
     */
    public function startEvaluation(): void
    {
        if ($this->status !== ProbationaryEvaluationStatus::Pending) {
            return;
        }

        $this->status = ProbationaryEvaluationStatus::Draft;
        $this->save();
    }

    /**
     * Submit the evaluation for HR review.
     */
    public function submit(): void
    {
        if (! in_array($this->status, [
            ProbationaryEvaluationStatus::Draft,
            ProbationaryEvaluationStatus::RevisionRequested,
        ])) {
            return;
        }

        $this->status = ProbationaryEvaluationStatus::Submitted;
        $this->submitted_at = now();
        $this->save();

        // Create HR approval record
        $this->approvals()->create([
            'approval_level' => 1,
            'approver_type' => 'hr',
            'decision' => ProbationaryApprovalDecision::Pending,
        ]);
    }

    /**
     * Mark as under HR review.
     */
    public function markAsHrReview(): void
    {
        if ($this->status !== ProbationaryEvaluationStatus::Submitted) {
            return;
        }

        $this->status = ProbationaryEvaluationStatus::HrReview;
        $this->save();
    }

    /**
     * Approve the evaluation.
     */
    public function approve(Employee $approver, ?string $remarks = null): void
    {
        $this->status = ProbationaryEvaluationStatus::Approved;
        $this->approved_at = now();
        $this->save();

        // Update the current approval record
        $approval = $this->currentApproval;
        if ($approval) {
            $approval->approver_employee_id = $approver->id;
            $approval->approver_name = $approver->full_name;
            $approval->approver_position = $approver->position?->name;
            $approval->decision = ProbationaryApprovalDecision::Approved;
            $approval->remarks = $remarks;
            $approval->decided_at = now();
            $approval->save();
        }
    }

    /**
     * Reject the evaluation.
     */
    public function reject(Employee $approver, string $reason): void
    {
        $this->status = ProbationaryEvaluationStatus::Rejected;
        $this->save();

        // Update the current approval record
        $approval = $this->currentApproval;
        if ($approval) {
            $approval->approver_employee_id = $approver->id;
            $approval->approver_name = $approver->full_name;
            $approval->approver_position = $approver->position?->name;
            $approval->decision = ProbationaryApprovalDecision::Rejected;
            $approval->remarks = $reason;
            $approval->decided_at = now();
            $approval->save();
        }
    }

    /**
     * Request revision from the manager.
     */
    public function requestRevision(Employee $approver, string $reason): void
    {
        $this->status = ProbationaryEvaluationStatus::RevisionRequested;
        $this->save();

        // Update the current approval record
        $approval = $this->currentApproval;
        if ($approval) {
            $approval->approver_employee_id = $approver->id;
            $approval->approver_name = $approver->full_name;
            $approval->approver_position = $approver->position?->name;
            $approval->decision = ProbationaryApprovalDecision::RevisionRequested;
            $approval->remarks = $reason;
            $approval->decided_at = now();
            $approval->save();
        }
    }

    /**
     * Calculate the overall rating from criteria ratings.
     */
    public function calculateOverallRating(): ?float
    {
        if (empty($this->criteria_ratings)) {
            return null;
        }

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($this->criteria_ratings as $rating) {
            $weight = $rating['weight'] ?? 1;
            $totalWeight += $weight;
            $weightedSum += ($rating['rating'] ?? 0) * $weight;
        }

        if ($totalWeight === 0) {
            return null;
        }

        return round($weightedSum / $totalWeight, 2);
    }
}
