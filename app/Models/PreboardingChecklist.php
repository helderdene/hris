<?php

namespace App\Models;

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A preboarding checklist assigned to a new hire after offer acceptance.
 */
class PreboardingChecklist extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PreboardingChecklistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'offer_id',
        'status',
        'deadline',
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
            'status' => PreboardingStatus::class,
            'deadline' => 'date',
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
     * Get the offer.
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the checklist items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PreboardingChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * Get the user who created this checklist.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the progress percentage of this checklist.
     */
    public function getProgressPercentageAttribute(): int
    {
        $total = $this->items()->where('is_required', true)->count();

        if ($total === 0) {
            return 100;
        }

        $approved = $this->items()
            ->where('is_required', true)
            ->where('status', PreboardingItemStatus::Approved)
            ->count();

        return (int) round(($approved / $total) * 100);
    }
}
