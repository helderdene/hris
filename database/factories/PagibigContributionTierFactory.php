<?php

namespace Database\Factories;

use App\Models\PagibigContributionTable;
use App\Models\PagibigContributionTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PagibigContributionTier>
 */
class PagibigContributionTierFactory extends Factory
{
    protected $model = PagibigContributionTier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pagibig_contribution_table_id' => PagibigContributionTable::factory(),
            'min_salary' => 0,
            'max_salary' => 1500.00,
            'employee_rate' => 0.0100,
            'employer_rate' => 0.0200,
        ];
    }

    /**
     * Configure the tier for a specific table.
     */
    public function forTable(PagibigContributionTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'pagibig_contribution_table_id' => $table->id,
        ]);
    }

    /**
     * Configure as the higher tier (over 1,500).
     */
    public function higherTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'min_salary' => 1500.01,
            'max_salary' => null,
            'employee_rate' => 0.0200,
            'employer_rate' => 0.0200,
        ]);
    }
}
