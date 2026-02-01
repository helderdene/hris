<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Position model for job title management.
 *
 * Positions can be linked to salary grades for compensation structure.
 */
class Position extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'code',
        'description',
        'salary_grade_id',
        'job_level',
        'employment_type',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_level' => JobLevel::class,
            'employment_type' => EmploymentType::class,
        ];
    }

    /**
     * Get the salary grade for this position.
     */
    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }

    /**
     * Get the position competencies (competency requirements by job level).
     */
    public function positionCompetencies(): HasMany
    {
        return $this->hasMany(PositionCompetency::class);
    }

    /**
     * Get competencies for a specific job level.
     */
    public function competenciesForLevel(JobLevel $level): HasMany
    {
        return $this->positionCompetencies()->where('job_level', $level->value);
    }

    /**
     * Scope to get only active positions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
