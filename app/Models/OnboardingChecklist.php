<?php

namespace App\Models;

use App\Enums\OnboardingItemStatus;
use App\Enums\OnboardingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An onboarding checklist assigned to a new employee after conversion.
 */
class OnboardingChecklist extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OnboardingChecklistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'onboarding_template_id',
        'status',
        'start_date',
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
            'status' => OnboardingStatus::class,
            'start_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the template this checklist was created from.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    /**
     * Get the checklist items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OnboardingChecklistItem::class)->orderBy('sort_order');
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

        $completed = $this->items()
            ->where('is_required', true)
            ->where('status', OnboardingItemStatus::Completed)
            ->count();

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get the count of completed items.
     */
    public function getCompletedCountAttribute(): int
    {
        return $this->items()
            ->whereIn('status', [OnboardingItemStatus::Completed, OnboardingItemStatus::Skipped])
            ->count();
    }

    /**
     * Get the total count of items.
     */
    public function getTotalCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get the count of pending items.
     */
    public function getPendingCountAttribute(): int
    {
        return $this->items()
            ->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress])
            ->count();
    }

    /**
     * Scope to get only active (non-completed) checklists.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [OnboardingStatus::Pending, OnboardingStatus::InProgress]);
    }
}
