<?php

namespace Database\Factories;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTemplateItem>
 */
class OnboardingTemplateItemFactory extends Factory
{
    protected $model = OnboardingTemplateItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(OnboardingCategory::cases());

        return [
            'onboarding_template_id' => OnboardingTemplate::factory(),
            'category' => $category,
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'assigned_role' => $category->defaultRole(),
            'is_required' => true,
            'sort_order' => fake()->numberBetween(1, 10),
            'due_days_offset' => fake()->numberBetween(-3, 14),
        ];
    }

    /**
     * Set the item category.
     */
    public function category(OnboardingCategory $category): static
    {
        return $this->state(fn () => [
            'category' => $category,
            'assigned_role' => $category->defaultRole(),
        ]);
    }

    /**
     * Set the assigned role.
     */
    public function assignedRole(OnboardingAssignedRole $role): static
    {
        return $this->state(fn () => ['assigned_role' => $role]);
    }

    /**
     * Mark the item as optional.
     */
    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }

    /**
     * Set due days offset.
     */
    public function dueInDays(int $days): static
    {
        return $this->state(fn () => ['due_days_offset' => $days]);
    }
}
