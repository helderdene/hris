<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An offer extended to a candidate for a job application.
 */
class Offer extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OfferFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'offer_template_id',
        'content',
        'status',
        'salary',
        'salary_currency',
        'salary_frequency',
        'benefits',
        'terms',
        'start_date',
        'expiry_date',
        'position_title',
        'department',
        'work_location',
        'employment_type',
        'pdf_path',
        'created_by',
        'revoked_by',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'declined_at',
        'expired_at',
        'revoked_at',
        'decline_reason',
        'revoke_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'benefits' => 'array',
            'salary' => 'decimal:2',
            'start_date' => 'date',
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * Get the offer template used.
     */
    public function offerTemplate(): BelongsTo
    {
        return $this->belongsTo(OfferTemplate::class);
    }

    /**
     * Get the signatures for this offer.
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(OfferSignature::class);
    }

    /**
     * Get the user who created this offer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the preboarding checklist for this offer.
     */
    public function preboardingChecklist(): HasOne
    {
        return $this->hasOne(PreboardingChecklist::class);
    }
}
