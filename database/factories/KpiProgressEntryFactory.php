<?php

namespace Database\Factories;

use App\Models\KpiAssignment;
use App\Models\KpiProgressEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KpiProgressEntry>
 */
class KpiProgressEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = KpiProgressEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kpi_assignment_id' => KpiAssignment::factory(),
            'value' => fake()->randomFloat(2, 0, 10000),
            'notes' => fake()->optional()->sentence(),
            'recorded_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'recorded_by' => User::factory(),
        ];
    }

    /**
     * Set a specific value.
     */
    public function withValue(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
        ]);
    }

    /**
     * Set a specific recorded date.
     */
    public function recordedAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'recorded_at' => $date,
        ]);
    }

    /**
     * Include notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes,
        ]);
    }
}
