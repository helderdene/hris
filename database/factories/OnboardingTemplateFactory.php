<?php

namespace Database\Factories;

use App\Models\OnboardingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingTemplate>
 */
class OnboardingTemplateFactory extends Factory
{
    protected $model = OnboardingTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Template',
            'description' => fake()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Mark the template as default.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Mark the template as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Create template with standard onboarding items.
     */
    public function withStandardItems(): static
    {
        return $this->afterCreating(function (OnboardingTemplate $template) {
            $template->items()->createMany([
                [
                    'category' => 'provisioning',
                    'name' => 'Create email account',
                    'description' => 'Set up company email address',
                    'assigned_role' => 'it',
                    'is_required' => true,
                    'sort_order' => 1,
                    'due_days_offset' => -1,
                ],
                [
                    'category' => 'provisioning',
                    'name' => 'Set up system access',
                    'description' => 'Configure access to required systems',
                    'assigned_role' => 'it',
                    'is_required' => true,
                    'sort_order' => 2,
                    'due_days_offset' => -1,
                ],
                [
                    'category' => 'equipment',
                    'name' => 'Assign laptop',
                    'description' => 'Prepare and assign a laptop',
                    'assigned_role' => 'admin',
                    'is_required' => true,
                    'sort_order' => 3,
                    'due_days_offset' => 0,
                ],
                [
                    'category' => 'orientation',
                    'name' => 'Welcome orientation',
                    'description' => 'Conduct welcome orientation session',
                    'assigned_role' => 'hr',
                    'is_required' => true,
                    'sort_order' => 4,
                    'due_days_offset' => 0,
                ],
                [
                    'category' => 'training',
                    'name' => 'Complete compliance training',
                    'description' => 'Assign and track compliance training modules',
                    'assigned_role' => 'hr',
                    'is_required' => true,
                    'sort_order' => 5,
                    'due_days_offset' => 7,
                ],
            ]);
        });
    }
}
