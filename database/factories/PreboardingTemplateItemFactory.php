<?php

namespace Database\Factories;

use App\Enums\PreboardingItemType;
use App\Models\PreboardingTemplate;
use App\Models\PreboardingTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreboardingTemplateItem>
 */
class PreboardingTemplateItemFactory extends Factory
{
    protected $model = PreboardingTemplateItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preboarding_template_id' => PreboardingTemplate::factory(),
            'type' => fake()->randomElement(PreboardingItemType::cases()),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_required' => true,
            'sort_order' => fake()->numberBetween(0, 20),
            'document_category_id' => null,
        ];
    }

    /**
     * Set the item as optional.
     */
    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }

    /**
     * Set the item type to document upload.
     */
    public function documentUpload(): static
    {
        return $this->state(fn () => ['type' => PreboardingItemType::DocumentUpload]);
    }

    /**
     * Set the item type to form field.
     */
    public function formField(): static
    {
        return $this->state(fn () => ['type' => PreboardingItemType::FormField]);
    }

    /**
     * Set the item type to acknowledgment.
     */
    public function acknowledgment(): static
    {
        return $this->state(fn () => ['type' => PreboardingItemType::Acknowledgment]);
    }
}
