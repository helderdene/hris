<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\EmployeeOffboarding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeOffboarding>
 */
class EmployeeOffboardingFactory extends Factory
{
    protected $model = EmployeeOffboarding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'separation_type' => EmploymentStatus::Resigned,
            'filed_date' => now()->toDateString(),
            'last_working_date' => now()->addDays(30)->toDateString(),
            'reason' => fake()->sentence(),
        ];
    }
}
