<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An interview scheduled for a job application.
 */
class Interview extends TenantModel
{
    /** @use HasFactory<\Database\Factories\InterviewFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'type',
        'status',
        'title',
        'scheduled_at',
        'duration_minutes',
        'location',
        'meeting_url',
        'notes',
        'cancelled_at',
        'cancellation_reason',
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
            'type' => InterviewType::class,
            'status' => InterviewStatus::class,
            'scheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
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
     * Get the panelists for this interview.
     */
    public function panelists(): HasMany
    {
        return $this->hasMany(InterviewPanelist::class);
    }

    /**
     * Get the employee who created this interview.
     */
    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
