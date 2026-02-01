<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ComplianceCourse model for compliance-specific course settings.
 *
 * Stores due dates, validity periods, passing requirements, and notification settings
 * for courses marked as compliance training.
 */
class ComplianceCourse extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceCourseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'days_to_complete',
        'validity_months',
        'passing_score',
        'max_attempts',
        'allow_retakes_after_pass',
        'requires_acknowledgment',
        'acknowledgment_text',
        'reminder_days',
        'escalation_days',
        'auto_reassign_on_expiry',
        'completion_message',
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
            'days_to_complete' => 'integer',
            'validity_months' => 'integer',
            'passing_score' => 'decimal:2',
            'max_attempts' => 'integer',
            'allow_retakes_after_pass' => 'boolean',
            'requires_acknowledgment' => 'boolean',
            'reminder_days' => 'array',
            'escalation_days' => 'array',
            'auto_reassign_on_expiry' => 'boolean',
        ];
    }

    /**
     * Get the parent course.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the employee who created this compliance course.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * Get the modules for this compliance course.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(ComplianceModule::class)->orderBy('sort_order');
    }

    /**
     * Get the assignment rules for this compliance course.
     */
    public function assignmentRules(): HasMany
    {
        return $this->hasMany(ComplianceAssignmentRule::class);
    }

    /**
     * Get the active assignment rules for this compliance course.
     */
    public function activeRules(): HasMany
    {
        return $this->assignmentRules()->active();
    }

    /**
     * Get the assignments for this compliance course.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ComplianceAssignment::class);
    }

    /**
     * Scope to get compliance courses for published courses only.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas('course', function ($q) {
            $q->published();
        });
    }

    /**
     * Scope to get compliance courses that require re-certification.
     */
    public function scopeRequiresRecertification(Builder $query): Builder
    {
        return $query->whereNotNull('validity_months');
    }

    /**
     * Get the total duration of all modules in minutes.
     */
    public function getTotalDurationMinutes(): int
    {
        return $this->modules()->sum('duration_minutes') ?? 0;
    }

    /**
     * Get the count of required modules.
     */
    public function getRequiredModulesCount(): int
    {
        return $this->modules()->where('is_required', true)->count();
    }

    /**
     * Get the count of assessment modules.
     */
    public function getAssessmentModulesCount(): int
    {
        return $this->modules()->where('content_type', 'assessment')->count();
    }

    /**
     * Check if the course has any assessments.
     */
    public function hasAssessments(): bool
    {
        return $this->getAssessmentModulesCount() > 0;
    }

    /**
     * Get the default reminder days if not set.
     *
     * @return array<int>
     */
    public function getReminderDaysAttribute(?array $value): array
    {
        return $value ?? [7, 3, 1];
    }

    /**
     * Get the default escalation days if not set.
     *
     * @return array<int>
     */
    public function getEscalationDaysAttribute(?array $value): array
    {
        return $value ?? [3, 7, 14];
    }
}
