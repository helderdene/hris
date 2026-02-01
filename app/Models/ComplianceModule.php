<?php

namespace App\Models;

use App\Enums\ComplianceModuleContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ComplianceModule model for compliance course content.
 *
 * Stores module content including video, text, PDF, SCORM, and assessment types.
 */
class ComplianceModule extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceModuleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_course_id',
        'title',
        'description',
        'content_type',
        'content',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'external_url',
        'duration_minutes',
        'sort_order',
        'is_required',
        'passing_score',
        'max_attempts',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => ComplianceModuleContentType::class,
            'file_size' => 'integer',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'passing_score' => 'decimal:2',
            'max_attempts' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * Get the compliance course this module belongs to.
     */
    public function complianceCourse(): BelongsTo
    {
        return $this->belongsTo(ComplianceCourse::class);
    }

    /**
     * Get the assessment questions for this module.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(ComplianceAssessment::class)->orderBy('sort_order');
    }

    /**
     * Get the active assessment questions for this module.
     */
    public function activeAssessments(): HasMany
    {
        return $this->assessments()->where('is_active', true);
    }

    /**
     * Get the progress records for this module.
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(ComplianceProgress::class);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to get only required modules.
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to filter by content type.
     */
    public function scopeOfType(Builder $query, ComplianceModuleContentType|string $type): Builder
    {
        $value = $type instanceof ComplianceModuleContentType ? $type->value : $type;

        return $query->where('content_type', $value);
    }

    /**
     * Check if this is an assessment module.
     */
    public function isAssessment(): bool
    {
        return $this->content_type === ComplianceModuleContentType::Assessment;
    }

    /**
     * Check if this is a video module.
     */
    public function isVideo(): bool
    {
        return $this->content_type === ComplianceModuleContentType::Video;
    }

    /**
     * Check if this module requires a file.
     */
    public function requiresFile(): bool
    {
        return $this->content_type->requiresFile();
    }

    /**
     * Get the effective passing score (module-level or course-level default).
     */
    public function getEffectivePassingScore(): float
    {
        if ($this->passing_score !== null) {
            return (float) $this->passing_score;
        }

        return (float) ($this->complianceCourse->passing_score ?? 80.00);
    }

    /**
     * Get the effective max attempts (module-level or course-level default).
     */
    public function getEffectiveMaxAttempts(): int
    {
        if ($this->max_attempts !== null) {
            return $this->max_attempts;
        }

        return $this->complianceCourse->max_attempts ?? 3;
    }

    /**
     * Get the total points available in this assessment module.
     */
    public function getTotalPoints(): int
    {
        if (! $this->isAssessment()) {
            return 0;
        }

        return $this->activeAssessments()->sum('points') ?? 0;
    }

    /**
     * Get the question count for this assessment module.
     */
    public function getQuestionCount(): int
    {
        if (! $this->isAssessment()) {
            return 0;
        }

        return $this->activeAssessments()->count();
    }
}
