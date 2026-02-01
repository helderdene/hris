<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Education record for a candidate.
 */
class CandidateEducation extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CandidateEducationFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'candidate_education';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidate_id',
        'education_level',
        'institution',
        'field_of_study',
        'start_date',
        'end_date',
        'is_current',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'education_level' => EducationLevel::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Get the candidate.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
