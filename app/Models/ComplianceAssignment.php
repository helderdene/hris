<?php

namespace App\Models;

use App\Enums\ComplianceAssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ComplianceAssignment model for employee training assignments.
 *
 * Tracks assignment status, due dates, and completion information
 * for compliance training assigned to employees.
 */
class ComplianceAssignment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceAssignmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_course_id',
        'employee_id',
        'assignment_rule_id',
        'status',
        'assigned_date',
        'due_date',
        'started_at',
        'completed_at',
        'final_score',
        'attempts_used',
        'total_time_minutes',
        'valid_until',
        'exemption_reason',
        'exempted_by',
        'exempted_at',
        'assigned_by',
        'acknowledgment_completed',
        'acknowledged_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplianceAssignmentStatus::class,
            'assigned_date' => 'date',
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_score' => 'decimal:2',
            'attempts_used' => 'integer',
            'total_time_minutes' => 'integer',
            'valid_until' => 'date',
            'exempted_at' => 'datetime',
            'acknowledgment_completed' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * Get the compliance course for this assignment.
     */
    public function complianceCourse(): BelongsTo
    {
        return $this->belongsTo(ComplianceCourse::class);
    }

    /**
     * Get the employee assigned to this training.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the rule that created this assignment.
     */
    public function assignmentRule(): BelongsTo
    {
        return $this->belongsTo(ComplianceAssignmentRule::class, 'assignment_rule_id');
    }

    /**
     * Get the employee who assigned this training.
     */
    public function assignedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * Get the employee who granted exemption.
     */
    public function exemptedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'exempted_by');
    }

    /**
     * Get the progress records for this assignment.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ComplianceProgress::class);
    }

    /**
     * Get the certificate for this assignment.
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(ComplianceCertificate::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, ComplianceAssignmentStatus|string $status): Builder
    {
        $value = $status instanceof ComplianceAssignmentStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to get pending assignments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ComplianceAssignmentStatus::Pending->value);
    }

    /**
     * Scope to get in-progress assignments.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ComplianceAssignmentStatus::InProgress->value);
    }

    /**
     * Scope to get completed assignments.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ComplianceAssignmentStatus::Completed->value);
    }

    /**
     * Scope to get overdue assignments.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ComplianceAssignmentStatus::Overdue->value);
    }

    /**
     * Scope to get active (non-terminal) assignments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ComplianceAssignmentStatus::Pending->value,
            ComplianceAssignmentStatus::InProgress->value,
            ComplianceAssignmentStatus::Overdue->value,
        ]);
    }

    /**
     * Scope to get assignments due soon (within given days).
     */
    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->active()
            ->where('due_date', '<=', now()->addDays($days))
            ->where('due_date', '>=', now());
    }

    /**
     * Scope to get assignments past due.
     */
    public function scopePastDue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ComplianceAssignmentStatus::Pending->value,
            ComplianceAssignmentStatus::InProgress->value,
        ])->where('due_date', '<', now());
    }

    /**
     * Scope to get assignments expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->completed()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>=', now());
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if the assignment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ComplianceAssignmentStatus::Completed;
    }

    /**
     * Check if the assignment is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        return $this->due_date && now()->startOfDay()->gt($this->due_date);
    }

    /**
     * Check if the assignment is due soon (within given days).
     */
    public function isDueSoon(int $days = 7): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        return $this->due_date
            && $this->due_date->gte(now())
            && $this->due_date->lte(now()->addDays($days));
    }

    /**
     * Check if the completion is expiring soon (within given days).
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->status !== ComplianceAssignmentStatus::Completed) {
            return false;
        }

        return $this->valid_until
            && $this->valid_until->gte(now())
            && $this->valid_until->lte(now()->addDays($days));
    }

    /**
     * Check if the completion has expired.
     */
    public function isExpired(): bool
    {
        if (! $this->valid_until) {
            return false;
        }

        return now()->startOfDay()->gt($this->valid_until);
    }

    /**
     * Get the number of days until due.
     */
    public function getDaysUntilDue(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date, false);
    }

    /**
     * Get the completion percentage based on module progress.
     */
    public function getCompletionPercentage(): float
    {
        $totalModules = $this->complianceCourse->modules()->where('is_required', true)->count();

        if ($totalModules === 0) {
            return 0.0;
        }

        $completedModules = $this->progress()
            ->where('status', 'completed')
            ->count();

        return round(($completedModules / $totalModules) * 100, 2);
    }

    /**
     * Start the assignment.
     */
    public function start(): bool
    {
        if ($this->status !== ComplianceAssignmentStatus::Pending) {
            return false;
        }

        $this->status = ComplianceAssignmentStatus::InProgress;
        $this->started_at = now();

        return $this->save();
    }

    /**
     * Complete the assignment.
     */
    public function complete(?float $finalScore = null): bool
    {
        $this->status = ComplianceAssignmentStatus::Completed;
        $this->completed_at = now();

        if ($finalScore !== null) {
            $this->final_score = $finalScore;
        }

        // Set validity period if applicable
        $validityMonths = $this->complianceCourse->validity_months;
        if ($validityMonths) {
            $this->valid_until = now()->addMonths($validityMonths);
        }

        return $this->save();
    }

    /**
     * Mark the assignment as overdue.
     */
    public function markOverdue(): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        $this->status = ComplianceAssignmentStatus::Overdue;

        return $this->save();
    }

    /**
     * Mark the assignment as expired.
     */
    public function markExpired(): bool
    {
        $this->status = ComplianceAssignmentStatus::Expired;

        return $this->save();
    }

    /**
     * Exempt the employee from this assignment.
     */
    public function exempt(Employee $exemptedBy, string $reason): bool
    {
        $this->status = ComplianceAssignmentStatus::Exempted;
        $this->exemption_reason = $reason;
        $this->exempted_by = $exemptedBy->id;
        $this->exempted_at = now();

        return $this->save();
    }
}
