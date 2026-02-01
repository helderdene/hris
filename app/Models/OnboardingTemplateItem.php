<?php

namespace App\Models;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item within an onboarding template.
 */
class OnboardingTemplateItem extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OnboardingTemplateItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'onboarding_template_id',
        'category',
        'name',
        'description',
        'assigned_role',
        'is_required',
        'sort_order',
        'due_days_offset',
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
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'due_days_offset' => 'integer',
        ];
    }

    /**
     * Get the parent template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }
}
