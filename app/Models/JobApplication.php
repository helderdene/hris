<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Job application linking a candidate to a job posting.
 */
class JobApplication extends TenantModel
{
    /** @use HasFactory<\Database\Factories\JobApplicationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidate_id',
        'job_posting_id',
        'status',
        'source',
        'cover_letter',
        'rejection_reason',
        'notes',
        'assigned_to_employee_id',
        'created_by',
        'applied_at',
        'screening_at',
        'interview_at',
        'assessment_at',
        'offer_at',
        'hired_at',
        'rejected_at',
        'withdrawn_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'applied_at' => 'datetime',
            'screening_at' => 'datetime',
            'interview_at' => 'datetime',
            'assessment_at' => 'datetime',
            'offer_at' => 'datetime',
            'hired_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (JobApplication $application) {
            if ($application->applied_at === null) {
                $application->applied_at = now();
            }
        });
    }

    /**
     * Get the candidate.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the job posting.
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Get the assigned employee.
     */
    public function assignedToEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    /**
     * Get the status history records.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(JobApplicationStatusHistory::class);
    }

    /**
     * Get the interviews for this application.
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * Get the assessments for this application.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get the background checks for this application.
     */
    public function backgroundChecks(): HasMany
    {
        return $this->hasMany(BackgroundCheck::class);
    }

    /**
     * Get the reference checks for this application.
     */
    public function referenceChecks(): HasMany
    {
        return $this->hasMany(ReferenceCheck::class);
    }

    /**
     * Get the offer for this application.
     */
    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    /**
     * Get the preboarding checklist for this application.
     */
    public function preboardingChecklist(): HasOne
    {
        return $this->hasOne(PreboardingChecklist::class);
    }

    /**
     * Scope to filter by job posting.
     */
    public function scopeForPosting(Builder $query, int|JobPosting $posting): Builder
    {
        $postingId = $posting instanceof JobPosting ? $posting->id : $posting;

        return $query->where('job_posting_id', $postingId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, ApplicationStatus|string $status): Builder
    {
        $value = $status instanceof ApplicationStatus ? $status->value : $status;

        return $query->where('status', $value);
    }
}
