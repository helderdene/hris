<?php

namespace Database\Factories;

use App\Enums\JobLevel;
use App\Models\Competency;
use App\Models\Position;
use App\Models\PositionCompetency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PositionCompetency>
 */
class PositionCompetencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PositionCompetency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'competency_id' => Competency::factory(),
            'job_level' => fake()->randomElement(JobLevel::cases()),
            'required_proficiency_level' => fake()->numberBetween(1, 5),
            'is_mandatory' => true,
            'weight' => fake()->randomFloat(2, 0.5, 2.0),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Set the position for this assignment.
     */
    public function forPosition(Position $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position_id' => $position->id,
        ]);
    }

    /**
     * Set the competency for this assignment.
     */
    public function forCompetency(Competency $competency): static
    {
        return $this->state(fn (array $attributes) => [
            'competency_id' => $competency->id,
        ]);
    }

    /**
     * Set the job level for this assignment.
     */
    public function forJobLevel(JobLevel $jobLevel): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => $jobLevel,
        ]);
    }

    /**
     * Set a specific proficiency level requirement.
     */
    public function withProficiencyLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'required_proficiency_level' => $level,
        ]);
    }

    /**
     * Mark as optional (not mandatory).
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => false,
        ]);
    }

    /**
     * Mark as mandatory.
     */
    public function mandatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => true,
        ]);
    }

    /**
     * Set a specific weight.
     */
    public function withWeight(float $weight): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => $weight,
        ]);
    }
}
