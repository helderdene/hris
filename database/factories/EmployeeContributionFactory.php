<?php

namespace Database\Factories;

use App\Enums\ContributionType;
use App\Models\Employee;
use App\Models\EmployeeContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeContribution>
 */
class EmployeeContributionFactory extends Factory
{
    protected $model = EmployeeContribution::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-3 months', 'now');
        $periodEnd = (clone $periodStart)->modify('+14 days');
        $basisSalary = fake()->numberBetween(15000, 50000);

        return [
            'employee_id' => Employee::factory(),
            'payroll_period_start' => $periodStart,
            'payroll_period_end' => $periodEnd,
            'contribution_type' => fake()->randomElement(ContributionType::values()),
            'basis_salary' => $basisSalary,
            'employee_share' => round($basisSalary * 0.045, 2),
            'employer_share' => round($basisSalary * 0.095, 2),
            'total_contribution' => round($basisSalary * 0.14, 2),
            'sss_ec_contribution' => null,
            'contribution_table_id' => null,
            'contribution_table_type' => null,
            'remarks' => null,
            'calculated_at' => now(),
            'calculated_by' => null,
        ];
    }

    /**
     * Configure for SSS contribution.
     */
    public function sss(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => ContributionType::Sss->value,
            'sss_ec_contribution' => 30,
        ]);
    }

    /**
     * Configure for PhilHealth contribution.
     */
    public function philhealth(): static
    {
        return $this->state(function (array $attributes) {
            $basisSalary = $attributes['basis_salary'];
            $contribution = $basisSalary * 0.05;
            $share = $contribution / 2;

            return [
                'contribution_type' => ContributionType::Philhealth->value,
                'employee_share' => $share,
                'employer_share' => $share,
                'total_contribution' => $contribution,
            ];
        });
    }

    /**
     * Configure for Pag-IBIG contribution.
     */
    public function pagibig(): static
    {
        return $this->state(function (array $attributes) {
            $basisSalary = min($attributes['basis_salary'], 5000);
            $employeeShare = $basisSalary * 0.02;
            $employerShare = $basisSalary * 0.02;

            return [
                'contribution_type' => ContributionType::Pagibig->value,
                'basis_salary' => $basisSalary,
                'employee_share' => $employeeShare,
                'employer_share' => $employerShare,
                'total_contribution' => $employeeShare + $employerShare,
            ];
        });
    }

    /**
     * Configure for a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }
}
