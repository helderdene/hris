<?php

namespace Database\Factories;

use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryStep>
 */
class SalaryStepFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = SalaryStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_grade_id' => SalaryGrade::factory(),
            'step_number' => fake()->numberBetween(1, 10),
            'amount' => fake()->numberBetween(30000, 150000),
            'effective_date' => fake()->optional()->date(),
        ];
    }

    /**
     * Set a specific step number.
     */
    public function step(int $stepNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'step_number' => $stepNumber,
        ]);
    }

    /**
     * Set an effective date.
     */
    public function withEffectiveDate(?\DateTimeInterface $date = null): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => $date ?? fake()->date(),
        ]);
    }
}
