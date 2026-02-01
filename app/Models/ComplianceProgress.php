<?php

namespace App\Models;

use App\Enums\ComplianceProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ComplianceProgress model for module-level progress tracking.
 *
 * Tracks individual module completion status and time spent
 * for compliance training assignments.
 */
class ComplianceProgress extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceProgressFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'compliance_progress';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_assignment_id',
        'compliance_module_id',
        'status',
        'started_at',
        'completed_at',
        'time_spent_minutes',
        'progress_percentage',
        'position_data',
        'best_score',
        'attempts_made',
        'last_accessed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplianceProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'time_spent_minutes' => 'integer',
            'progress_percentage' => 'decimal:2',
            'position_data' => 'array',
            'best_score' => 'decimal:2',
            'attempts_made' => 'integer',
            'last_accessed_at' => 'datetime',
        ];
    }

    /**
     * Get the assignment this progress belongs to.
     */
    public function complianceAssignment(): BelongsTo
    {
        return $this->belongsTo(ComplianceAssignment::class);
    }

    /**
     * Get the module this progress is for.
     */
    public function complianceModule(): BelongsTo
    {
        return $this->belongsTo(ComplianceModule::class);
    }

    /**
     * Get the assessment attempts for this progress.
     */
    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(ComplianceAssessmentAttempt::class)->orderBy('attempt_number');
    }

    /**
     * Get the latest assessment attempt.
     */
    public function latestAttempt(): ?ComplianceAssessmentAttempt
    {
        return $this->assessmentAttempts()->latest('attempt_number')->first();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, ComplianceProgressStatus|string $status): Builder
    {
        $value = $status instanceof ComplianceProgressStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to get completed progress.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ComplianceProgressStatus::Completed->value);
    }

    /**
     * Scope to get in-progress records.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ComplianceProgressStatus::InProgress->value);
    }

    /**
     * Scope to get not started records.
     */
    public function scopeNotStarted(Builder $query): Builder
    {
        return $query->where('status', ComplianceProgressStatus::NotStarted->value);
    }

    /**
     * Check if the module is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ComplianceProgressStatus::Completed;
    }

    /**
     * Check if the module is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === ComplianceProgressStatus::InProgress;
    }

    /**
     * Check if the module has not been started.
     */
    public function isNotStarted(): bool
    {
        return $this->status === ComplianceProgressStatus::NotStarted;
    }

    /**
     * Start the module.
     */
    public function start(): bool
    {
        if ($this->status !== ComplianceProgressStatus::NotStarted) {
            return false;
        }

        $this->status = ComplianceProgressStatus::InProgress;
        $this->started_at = now();
        $this->last_accessed_at = now();

        return $this->save();
    }

    /**
     * Update progress.
     */
    public function updateProgress(float $percentage, ?array $positionData = null): bool
    {
        $this->progress_percentage = min(100, max(0, $percentage));
        $this->last_accessed_at = now();

        if ($positionData !== null) {
            $this->position_data = $positionData;
        }

        return $this->save();
    }

    /**
     * Add time spent.
     */
    public function addTimeSpent(int $minutes): bool
    {
        $this->time_spent_minutes += $minutes;
        $this->last_accessed_at = now();

        return $this->save();
    }

    /**
     * Complete the module.
     */
    public function complete(?float $score = null): bool
    {
        $this->status = ComplianceProgressStatus::Completed;
        $this->completed_at = now();
        $this->progress_percentage = 100;
        $this->last_accessed_at = now();

        if ($score !== null) {
            if ($this->best_score === null || $score > $this->best_score) {
                $this->best_score = $score;
            }
        }

        return $this->save();
    }

    /**
     * Mark the module as failed.
     */
    public function fail(): bool
    {
        $this->status = ComplianceProgressStatus::Failed;
        $this->last_accessed_at = now();

        return $this->save();
    }

    /**
     * Check if the employee can make another attempt.
     */
    public function canAttempt(): bool
    {
        $maxAttempts = $this->complianceModule->getEffectiveMaxAttempts();

        return $this->attempts_made < $maxAttempts;
    }

    /**
     * Get the remaining attempts.
     */
    public function getRemainingAttempts(): int
    {
        $maxAttempts = $this->complianceModule->getEffectiveMaxAttempts();

        return max(0, $maxAttempts - $this->attempts_made);
    }
}
