<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\GoalProgressEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalProgressEntry>
 */
class GoalProgressEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = GoalProgressEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'goal_key_result_id' => null,
            'progress_value' => fake()->randomFloat(2, 0, 100),
            'progress_percentage' => fake()->randomFloat(2, 0, 100),
            'notes' => fake()->optional()->sentence(),
            'recorded_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'recorded_by' => User::factory(),
        ];
    }

    /**
     * Associate with a key result.
     */
    public function forKeyResult(GoalKeyResult $keyResult): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_id' => $keyResult->goal_id,
            'goal_key_result_id' => $keyResult->id,
        ]);
    }

    /**
     * Set specific progress values.
     */
    public function withProgress(float $value, ?float $percentage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_value' => $value,
            'progress_percentage' => $percentage,
        ]);
    }

    /**
     * Add notes to the entry.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }

    /**
     * Set a specific recorded date.
     */
    public function recordedAt(\DateTime|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $date,
        ]);
    }
}
