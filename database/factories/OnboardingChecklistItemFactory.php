<?php

namespace Database\Factories;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingChecklistItem>
 */
class OnboardingChecklistItemFactory extends Factory
{
    protected $model = OnboardingChecklistItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(OnboardingCategory::cases());

        return [
            'onboarding_checklist_id' => OnboardingChecklist::factory(),
            'onboarding_template_item_id' => null,
            'category' => $category,
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'assigned_role' => $category->defaultRole(),
            'assigned_to' => null,
            'is_required' => true,
            'sort_order' => fake()->numberBetween(1, 10),
            'due_date' => now()->addDays(fake()->numberBetween(1, 14)),
            'status' => OnboardingItemStatus::Pending,
            'notes' => null,
            'equipment_details' => null,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    /**
     * Set the item status.
     */
    public function withStatus(OnboardingItemStatus $status): static
    {
        $data = ['status' => $status];

        if ($status === OnboardingItemStatus::Completed) {
            $data['completed_at'] = now();
        }

        return $this->state(fn () => $data);
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
     * Assign to a specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->id]);
    }

    /**
     * Mark item as completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OnboardingItemStatus::Completed,
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    /**
     * Mark as completed by a specific user.
     */
    public function completedBy(User $user): static
    {
        return $this->state(fn () => [
            'status' => OnboardingItemStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);
    }

    /**
     * Mark the item as optional.
     */
    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }

    /**
     * Add equipment details.
     */
    public function withEquipment(array $details): static
    {
        return $this->state(fn () => [
            'category' => OnboardingCategory::Equipment,
            'assigned_role' => OnboardingAssignedRole::Admin,
            'equipment_details' => $details,
        ]);
    }

    /**
     * Set as overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(3),
            'status' => OnboardingItemStatus::Pending,
        ]);
    }
}
