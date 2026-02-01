<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Candidate model for the applicant tracking system.
 */
class Candidate extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CandidateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'linkedin_url',
        'portfolio_url',
        'resume_file_path',
        'resume_file_name',
        'resume_parsed_text',
        'skills',
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
            'date_of_birth' => 'date',
            'skills' => 'array',
        ];
    }

    /**
     * Get the candidate's education records.
     */
    public function education(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    /**
     * Get the candidate's work experience records.
     */
    public function workExperiences(): HasMany
    {
        return $this->hasMany(CandidateWorkExperience::class);
    }

    /**
     * Get the candidate's job applications.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get the user who created this candidate.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the candidate's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->first_name.' '.$this->last_name
        );
    }

    /**
     * Scope to search by name or email.
     */
    public function scopeSearchByNameOrEmail(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
