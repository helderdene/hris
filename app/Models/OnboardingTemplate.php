<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable onboarding template with items that can be assigned to new employees.
 */
class OnboardingTemplate extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OnboardingTemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
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
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the template items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OnboardingTemplateItem::class)->orderBy('sort_order');
    }

    /**
     * Get the checklists created from this template.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(OnboardingChecklist::class);
    }

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get the default template.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
