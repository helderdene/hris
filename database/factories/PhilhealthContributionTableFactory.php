<?php

namespace Database\Factories;

use App\Models\PhilhealthContributionTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PhilhealthContributionTable>
 */
class PhilhealthContributionTableFactory extends Factory
{
    protected $model = PhilhealthContributionTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => 'PhilHealth Contribution Table '.fake()->year(),
            'contribution_rate' => 0.0500,
            'employee_share_rate' => 0.5000,
            'employer_share_rate' => 0.5000,
            'salary_floor' => 10000.00,
            'salary_ceiling' => 100000.00,
            'min_contribution' => 500.00,
            'max_contribution' => 5000.00,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the table is for 2025.
     */
    public function year2025(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => '2025-01-01',
            'description' => '2025 PhilHealth Contribution Table',
            'contribution_rate' => 0.0500,
            'employee_share_rate' => 0.5000,
            'employer_share_rate' => 0.5000,
            'salary_floor' => 10000.00,
            'salary_ceiling' => 100000.00,
            'min_contribution' => 500.00,
            'max_contribution' => 5000.00,
        ]);
    }

    /**
     * Indicate that the table is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the creator.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
