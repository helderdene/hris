<?php

namespace Database\Factories;

use App\Models\SssContributionBracket;
use App\Models\SssContributionTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SssContributionBracket>
 */
class SssContributionBracketFactory extends Factory
{
    protected $model = SssContributionBracket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minSalary = fake()->numberBetween(5000, 25000);
        $maxSalary = $minSalary + 499.99;
        $msc = round($minSalary / 500) * 500;
        $employeeContribution = $msc * 0.045;
        $employerContribution = $msc * 0.095;

        return [
            'sss_contribution_table_id' => SssContributionTable::factory(),
            'min_salary' => $minSalary,
            'max_salary' => $maxSalary,
            'monthly_salary_credit' => $msc,
            'employee_contribution' => $employeeContribution,
            'employer_contribution' => $employerContribution,
            'total_contribution' => $employeeContribution + $employerContribution,
            'ec_contribution' => $msc >= 15000 ? 30 : 10,
        ];
    }

    /**
     * Configure the bracket for a specific table.
     */
    public function forTable(SssContributionTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'sss_contribution_table_id' => $table->id,
        ]);
    }
}
