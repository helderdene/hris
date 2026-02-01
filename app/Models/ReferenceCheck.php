<?php

namespace App\Models;

use App\Enums\ReferenceRecommendation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reference check for a job application.
 */
class ReferenceCheck extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ReferenceCheckFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'referee_name',
        'referee_email',
        'referee_phone',
        'referee_company',
        'relationship',
        'contacted',
        'contacted_at',
        'feedback',
        'recommendation',
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
            'contacted' => 'boolean',
            'contacted_at' => 'datetime',
            'recommendation' => ReferenceRecommendation::class,
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
     * Get the employee who created this reference check.
     */
    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
