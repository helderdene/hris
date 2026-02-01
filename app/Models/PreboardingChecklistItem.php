<?php

namespace App\Models;

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An individual item within a preboarding checklist.
 */
class PreboardingChecklistItem extends TenantModel
{
    /** @use HasFactory<\Database\Factories\PreboardingChecklistItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'preboarding_checklist_id',
        'preboarding_template_item_id',
        'type',
        'name',
        'description',
        'is_required',
        'sort_order',
        'status',
        'document_id',
        'document_category_id',
        'form_value',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
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
            'status' => PreboardingItemStatus::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent checklist.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(PreboardingChecklist::class, 'preboarding_checklist_id');
    }

    /**
     * Get the template item this was copied from.
     */
    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(PreboardingTemplateItem::class, 'preboarding_template_item_id');
    }

    /**
     * Get the uploaded document.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the document category.
     */
    public function documentCategory(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    /**
     * Get the user who reviewed this item.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
