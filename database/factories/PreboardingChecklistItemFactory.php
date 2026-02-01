<?php

namespace Database\Factories;

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingItemType;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreboardingChecklistItem>
 */
class PreboardingChecklistItemFactory extends Factory
{
    protected $model = PreboardingChecklistItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preboarding_checklist_id' => PreboardingChecklist::factory(),
            'preboarding_template_item_id' => null,
            'type' => fake()->randomElement(PreboardingItemType::cases()),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_required' => true,
            'sort_order' => fake()->numberBetween(0, 20),
            'status' => PreboardingItemStatus::Pending,
            'document_id' => null,
            'document_category_id' => null,
            'form_value' => null,
            'rejection_reason' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    /**
     * Set the item as submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => PreboardingItemStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set the item as approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => PreboardingItemStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Set the item as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => PreboardingItemStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
