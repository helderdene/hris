<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = JobPosting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'created_by_employee_id' => Employee::factory(),
            'title' => fake()->jobTitle().' '.fake()->word(),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraphs(2, true),
            'benefits' => fake()->paragraphs(1, true),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'location' => fake()->city(),
            'salary_display_option' => SalaryDisplayOption::ExactRange,
            'salary_range_min' => fake()->numberBetween(20000, 40000),
            'salary_range_max' => fake()->numberBetween(40000, 80000),
            'application_instructions' => fake()->sentence(),
            'status' => JobPostingStatus::Draft,
            'published_at' => null,
            'closed_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPostingStatus::Draft,
        ]);
    }

    /**
     * Set as published status.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPostingStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Set as closed status.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPostingStatus::Closed,
            'published_at' => now()->subDays(30),
            'closed_at' => now(),
        ]);
    }

    /**
     * Set as archived status.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPostingStatus::Archived,
            'published_at' => now()->subDays(60),
            'closed_at' => now()->subDays(30),
        ]);
    }

    /**
     * Create from a job requisition.
     */
    public function fromRequisition(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_requisition_id' => JobRequisition::factory()->approved(),
        ]);
    }
}
