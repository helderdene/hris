<?php

namespace App\Models;

use App\Enums\BackgroundCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A background check for a job application.
 */
class BackgroundCheck extends TenantModel
{
    /** @use HasFactory<\Database\Factories\BackgroundCheckFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'check_type',
        'status',
        'provider',
        'notes',
        'started_at',
        'completed_at',
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
            'status' => BackgroundCheckStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * Get the documents for this background check.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(BackgroundCheckDocument::class);
    }

    /**
     * Get the employee who created this background check.
     */
    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
