<?php

namespace Database\Factories;

use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryGrade>
 */
class SalaryGradeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = SalaryGrade::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minimum = fake()->numberBetween(30000, 100000);
        $midpoint = $minimum + fake()->numberBetween(10000, 30000);
        $maximum = $midpoint + fake()->numberBetween(10000, 30000);

        return [
            'name' => 'Grade '.fake()->unique()->randomLetter().fake()->numberBetween(1, 99),
            'minimum_salary' => $minimum,
            'midpoint_salary' => $midpoint,
            'maximum_salary' => $maximum,
            'currency' => 'PHP',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the salary grade is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the salary grade is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create salary steps for this grade.
     */
    public function withSteps(int $count = 5): static
    {
        return $this->afterCreating(function (SalaryGrade $salaryGrade) use ($count) {
            $baseAmount = (float) $salaryGrade->minimum_salary;
            $increment = ((float) $salaryGrade->maximum_salary - $baseAmount) / max($count - 1, 1);

            for ($i = 1; $i <= $count; $i++) {
                SalaryStep::factory()->create([
                    'salary_grade_id' => $salaryGrade->id,
                    'step_number' => $i,
                    'amount' => $baseAmount + ($increment * ($i - 1)),
                ]);
            }
        });
    }
}
