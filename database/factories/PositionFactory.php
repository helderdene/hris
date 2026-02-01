<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Models\Position;
use App\Models\SalaryGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Position::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'code' => fake()->unique()->regexify('[A-Z]{2,4}-[0-9]{3}'),
            'description' => fake()->optional()->paragraph(),
            'salary_grade_id' => null,
            'job_level' => fake()->randomElement(JobLevel::cases()),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the position is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the position is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Associate with a salary grade.
     */
    public function withSalaryGrade(?SalaryGrade $salaryGrade = null): static
    {
        return $this->state(fn (array $attributes) => [
            'salary_grade_id' => $salaryGrade?->id ?? SalaryGrade::factory(),
        ]);
    }

    /**
     * Create a junior level position.
     */
    public function junior(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Junior,
        ]);
    }

    /**
     * Create a mid level position.
     */
    public function mid(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Mid,
        ]);
    }

    /**
     * Create a senior level position.
     */
    public function senior(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Senior,
        ]);
    }

    /**
     * Create a lead level position.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Lead,
        ]);
    }

    /**
     * Create a manager level position.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Manager,
        ]);
    }

    /**
     * Create a director level position.
     */
    public function director(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Director,
        ]);
    }

    /**
     * Create an executive level position.
     */
    public function executive(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_level' => JobLevel::Executive,
        ]);
    }

    /**
     * Create a regular employment type position.
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Regular,
        ]);
    }

    /**
     * Create a contractual employment type position.
     */
    public function contractual(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Contractual,
        ]);
    }

    /**
     * Create an intern employment type position.
     */
    public function intern(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Intern,
        ]);
    }
}
