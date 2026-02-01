<?php

namespace Database\Factories;

use App\Models\PagibigContributionTable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PagibigContributionTable>
 */
class PagibigContributionTableFactory extends Factory
{
    protected $model = PagibigContributionTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => 'Pag-IBIG Contribution Table '.fake()->year(),
            'max_monthly_compensation' => 5000.00,
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
            'description' => '2025 Pag-IBIG Contribution Table',
            'max_monthly_compensation' => 5000.00,
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

    /**
     * Configure the model factory to create with standard 2025 Pag-IBIG tiers.
     */
    public function withTiers(): static
    {
        return $this->afterCreating(function (PagibigContributionTable $table) {
            $tiers = $this->get2025Tiers();

            foreach ($tiers as $tier) {
                $table->tiers()->create($tier);
            }
        });
    }

    /**
     * Get the official 2025 Pag-IBIG contribution tiers.
     *
     * @return array<array<string, mixed>>
     */
    protected function get2025Tiers(): array
    {
        return [
            [
                'min_salary' => 0,
                'max_salary' => 1500.00,
                'employee_rate' => 0.0100,
                'employer_rate' => 0.0200,
            ],
            [
                'min_salary' => 1500.01,
                'max_salary' => null,
                'employee_rate' => 0.0200,
                'employer_rate' => 0.0200,
            ],
        ];
    }
}
