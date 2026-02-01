<?php

namespace App\Models;

use App\Enums\AssessmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A skills assessment for a job application.
 */
class Assessment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\AssessmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'test_name',
        'type',
        'score',
        'max_score',
        'passed',
        'assessed_at',
        'notes',
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
            'type' => AssessmentType::class,
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'passed' => 'boolean',
            'assessed_at' => 'datetime',
        ];
    }

    /**
     * Get the job application.
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    /**
     * Get the employee who created this assessment.
     */
    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
