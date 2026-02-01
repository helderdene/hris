<?php

namespace App\Models;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An individual item within an onboarding checklist.
 */
class OnboardingChecklistItem extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OnboardingChecklistItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'onboarding_checklist_id',
        'onboarding_template_item_id',
        'category',
        'name',
        'description',
        'assigned_role',
        'assigned_to',
        'is_required',
        'sort_order',
        'due_date',
        'status',
        'notes',
        'equipment_details',
        'completed_at',
        'completed_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => OnboardingCategory::class,
            'assigned_role' => OnboardingAssignedRole::class,
            'status' => OnboardingItemStatus::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'equipment_details' => 'array',
        ];
    }

    /**
     * Get the parent checklist.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklist::class, 'onboarding_checklist_id');
    }

    /**
     * Get the template item this was copied from.
     */
    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplateItem::class, 'onboarding_template_item_id');
    }

    /**
     * Get the user assigned to this item.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who completed this item.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Check if this item is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status->isDone()) {
            return false;
        }

        if ($this->due_date === null) {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Scope to filter by assigned role.
     */
    public function scopeForRole($query, OnboardingAssignedRole $role)
    {
        return $query->where('assigned_role', $role);
    }

    /**
     * Scope to filter by assigned user.
     */
    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to get only pending items.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress]);
    }
}
