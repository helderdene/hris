<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SalaryStep model for salary increments within a grade.
 *
 * Steps are ordered by step_number within each salary grade.
 */
class SalaryStep extends TenantModel
{
    /** @use HasFactory<\Database\Factories\SalaryStepFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'salary_grade_id',
        'step_number',
        'amount',
        'effective_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'amount' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    /**
     * Get the salary grade this step belongs to.
     */
    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }
}
