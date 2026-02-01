<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SalaryGrade model for compensation structure.
 *
 * Contains salary ranges with minimum, midpoint, and maximum values.
 * Validation: minimum <= midpoint <= maximum is enforced at model level.
 */
class SalaryGrade extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SalaryGradeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'minimum_salary',
        'midpoint_salary',
        'maximum_salary',
        'currency',
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
            'minimum_salary' => 'decimal:2',
            'midpoint_salary' => 'decimal:2',
            'maximum_salary' => 'decimal:2',
        ];
    }

    /**
     * Get the salary steps for this grade.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(SalaryStep::class)->orderBy('step_number');
    }

    /**
     * Get the positions that use this salary grade.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Scope to get only active salary grades.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Validate that the salary range is valid.
     *
     * Returns true if minimum <= midpoint <= maximum.
     */
    public function isValidSalaryRange(): bool
    {
        $min = (float) $this->minimum_salary;
        $mid = (float) $this->midpoint_salary;
        $max = (float) $this->maximum_salary;

        return $min <= $mid && $mid <= $max;
    }
}
