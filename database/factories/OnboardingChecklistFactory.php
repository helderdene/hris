<?php

namespace Database\Factories;

use App\Enums\OnboardingStatus;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingChecklist>
 */
class OnboardingChecklistFactory extends Factory
{
    protected $model = OnboardingChecklist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'onboarding_template_id' => OnboardingTemplate::factory(),
            'status' => OnboardingStatus::Pending,
            'start_date' => now()->addDays(7)->toDateString(),
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Set the checklist status.
     */
    public function withStatus(OnboardingStatus $status): static
    {
        $data = ['status' => $status];

        if ($status === OnboardingStatus::Completed) {
            $data['completed_at'] = now();
        }

        return $this->state(fn () => $data);
    }

    /**
     * Set the start date.
     */
    public function startingOn(\DateTimeInterface|string $date): static
    {
        return $this->state(fn () => ['start_date' => $date]);
    }

    /**
     * Mark as in progress.
     */
    public function inProgress(): static
    {
        return $this->withStatus(OnboardingStatus::InProgress);
    }

    /**
     * Mark as completed.
     */
    public function completed(): static
    {
        return $this->withStatus(OnboardingStatus::Completed);
    }

    /**
     * Create checklist with items from template.
     */
    public function withItemsFromTemplate(): static
    {
        return $this->afterCreating(function (OnboardingChecklist $checklist) {
            if ($checklist->template === null) {
                return;
            }

            foreach ($checklist->template->items as $templateItem) {
                $checklist->items()->create([
                    'onboarding_template_item_id' => $templateItem->id,
                    'category' => $templateItem->category,
                    'name' => $templateItem->name,
                    'description' => $templateItem->description,
                    'assigned_role' => $templateItem->assigned_role,
                    'is_required' => $templateItem->is_required,
                    'sort_order' => $templateItem->sort_order,
                    'due_date' => $checklist->start_date->addDays($templateItem->due_days_offset),
                    'status' => 'pending',
                ]);
            }
        });
    }
}
