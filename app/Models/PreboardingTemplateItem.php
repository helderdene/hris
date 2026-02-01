<?php

namespace App\Models;

use App\Enums\PreboardingItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item within a preboarding template.
 */
class PreboardingTemplateItem extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PreboardingTemplateItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'preboarding_template_id',
        'type',
        'name',
        'description',
        'is_required',
        'sort_order',
        'document_category_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PreboardingItemType::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PreboardingTemplate::class, 'preboarding_template_id');
    }

    /**
     * Get the document category.
     */
    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }
}
