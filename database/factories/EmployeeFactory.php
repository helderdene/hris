<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            // Core identification
            'user_id' => null,
            'employee_number' => fake()->unique()->numerify('EMP-######'),

            // Personal info
            'first_name' => $firstName,
            'middle_name' => fake()->optional(0.7)->firstName(),
            'last_name' => $lastName,
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III', 'IV']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'civil_status' => fake()->randomElement(['single', 'married', 'widowed', 'separated', 'divorced']),
            'nationality' => fake()->optional(0.9)->randomElement(['Filipino', 'American', 'Chinese', 'Japanese']),
            'fathers_name' => fake()->optional(0.8)->name('male'),
            'mothers_name' => fake()->optional(0.8)->name('female'),

            // Government IDs - random strings (no format validation per spec)
            'tin' => fake()->optional(0.9)->numerify('###-###-###-###'),
            'sss_number' => fake()->optional(0.9)->numerify('##-#######-#'),
            'philhealth_number' => fake()->optional(0.9)->numerify('##-#########-#'),
            'pagibig_number' => fake()->optional(0.9)->numerify('####-####-####'),
            'umid' => fake()->optional(0.3)->numerify('####-#######-#'),
            'passport_number' => fake()->optional(0.2)->regexify('[A-Z][0-9]{8}'),
            'drivers_license' => fake()->optional(0.3)->regexify('[A-Z][0-9]{2}-[0-9]{2}-[0-9]{6}'),
            'nbi_clearance' => fake()->optional(0.4)->regexify('[A-Z][0-9]{10}'),
            'police_clearance' => fake()->optional(0.3)->regexify('[0-9]{10}-[A-Z]{2}'),
            'prc_license' => fake()->optional(0.1)->numerify('#######'),

            // Employment relationships (null by default, set via states)
            'department_id' => null,
            'position_id' => null,
            'work_location_id' => null,
            'supervisor_id' => null,

            // Employment details
            'employment_type' => EmploymentType::Regular,
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'regularization_date' => null,
            'termination_date' => null,
            'basic_salary' => fake()->randomFloat(2, 15000, 200000),
            'pay_frequency' => fake()->randomElement(['monthly', 'semi-monthly', 'weekly']),

            // JSON fields
            'address' => [
                'street' => fake()->streetAddress(),
                'barangay' => 'Barangay '.fake()->numberBetween(1, 100),
                'city' => fake()->city(),
                'province' => fake()->state(),
                'postal_code' => fake()->postcode(),
            ],
            'emergency_contact' => [
                'name' => fake()->name(),
                'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling', 'friend']),
                'phone' => fake()->phoneNumber(),
            ],
            'education' => [
                'highest_attainment' => fake()->randomElement(['high_school', 'vocational', 'bachelors', 'masters', 'doctorate']),
                'school_name' => fake()->company().' University',
                'course' => fake()->randomElement(['Computer Science', 'Business Administration', 'Engineering', 'Education', 'Nursing']),
                'year_graduated' => (string) fake()->numberBetween(1990, 2024),
            ],
            'work_history' => [],
        ];
    }

    /**
     * Indicate that the employee is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Active,
            'termination_date' => null,
        ]);
    }

    /**
     * Indicate that the employee is on probation.
     */
    public function probationary(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Probationary,
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => fake()->dateTimeBetween('-6 months', '-1 week'),
            'regularization_date' => null,
        ]);
    }

    /**
     * Indicate that the employee is regular (regularized).
     */
    public function regular(): static
    {
        $hireDate = fake()->dateTimeBetween('-5 years', '-7 months');

        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Regular,
            'employment_status' => EmploymentStatus::Active,
            'hire_date' => $hireDate,
            'regularization_date' => fake()->dateTimeBetween($hireDate, '-1 month'),
        ]);
    }

    /**
     * Indicate that the employee is contractual.
     */
    public function contractual(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Contractual,
            'employment_status' => EmploymentStatus::Active,
        ]);
    }

    /**
     * Indicate that the employee is project-based.
     */
    public function projectBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::ProjectBased,
            'employment_status' => EmploymentStatus::Active,
        ]);
    }

    /**
     * Indicate that the employee has terminated (general separation).
     */
    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Terminated,
            'termination_date' => fake()->dateTimeBetween('-1 year', '-1 week'),
        ]);
    }

    /**
     * Indicate that the employee has resigned.
     */
    public function resigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Resigned,
            'termination_date' => fake()->dateTimeBetween('-1 year', '-1 week'),
        ]);
    }

    /**
     * Indicate that the employee has retired.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => EmploymentStatus::Retired,
            'termination_date' => fake()->dateTimeBetween('-2 years', '-1 week'),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-60 years'),
        ]);
    }

    /**
     * Indicate that the employee's contract has ended.
     */
    public function endOfContract(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => EmploymentType::Contractual,
            'employment_status' => EmploymentStatus::EndOfContract,
            'termination_date' => fake()->dateTimeBetween('-6 months', '-1 week'),
        ]);
    }

    /**
     * Indicate that the employee has an associated user account.
     */
    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => \App\Models\User::factory(),
        ]);
    }

    /**
     * Add previous work history.
     *
     * @param  int  $count  Number of previous jobs
     */
    public function withWorkHistory(int $count = 2): static
    {
        return $this->state(function (array $attributes) use ($count) {
            $workHistory = [];
            for ($i = 0; $i < $count; $i++) {
                $startDate = fake()->dateTimeBetween('-15 years', '-2 years');
                $endDate = fake()->dateTimeBetween($startDate, '-1 year');
                $workHistory[] = [
                    'company_name' => fake()->company(),
                    'position' => fake()->jobTitle(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'reason_for_leaving' => fake()->randomElement([
                        'Career growth',
                        'Better opportunity',
                        'Relocation',
                        'Contract ended',
                        'Personal reasons',
                    ]),
                ];
            }

            return ['work_history' => $workHistory];
        });
    }
}
