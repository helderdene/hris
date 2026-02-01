<?php

namespace App\Models;

use App\Enums\JobLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PositionCompetency model for the matrix linking competencies
 * to positions by job level.
 *
 * This allows different proficiency expectations for the same
 * competency based on the job level within a position.
 */
class PositionCompetency extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PositionCompetencyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'position_id',
        'competency_id',
        'job_level',
        'required_proficiency_level',
        'is_mandatory',
        'weight',
        'notes',
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
            'required_proficiency_level' => 'integer',
            'is_mandatory' => 'boolean',
            'weight' => 'decimal:2',
        ];
    }

    /**
     * Get the position this competency assignment belongs to.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the competency definition.
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    /**
     * Get the proficiency level definition.
     */
    public function proficiencyLevel(): BelongsTo
    {
        return $this->belongsTo(ProficiencyLevel::class, 'required_proficiency_level', 'level');
    }

    /**
     * Get the evaluations for this position competency.
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(CompetencyEvaluation::class);
    }

    /**
     * Scope to filter by position.
     */
    public function scopeForPosition(Builder $query, int $positionId): Builder
    {
        return $query->where('position_id', $positionId);
    }

    /**
     * Scope to filter by job level.
     */
    public function scopeForJobLevel(Builder $query, JobLevel|string $jobLevel): Builder
    {
        $value = $jobLevel instanceof JobLevel ? $jobLevel->value : $jobLevel;

        return $query->where('job_level', $value);
    }

    /**
     * Scope to filter mandatory competencies.
     */
    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope to filter optional competencies.
     */
    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_mandatory', false);
    }

    /**
     * Scope to include only active competencies.
     */
    public function scopeWithActiveCompetency(Builder $query): Builder
    {
        return $query->whereHas('competency', function ($q) {
            $q->where('is_active', true);
        });
    }
}
