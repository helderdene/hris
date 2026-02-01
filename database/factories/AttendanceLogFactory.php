<?php

namespace Database\Factories;

use App\Models\BiometricDevice;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory();

        return [
            'biometric_device_id' => BiometricDevice::factory(),
            'employee_id' => $employee,
            'device_person_id' => (string) fake()->numberBetween(1, 999),
            'device_record_id' => (string) fake()->numberBetween(1, 99999),
            'employee_code' => fn (array $attributes) => Employee::find($attributes['employee_id'])?->employee_number ?? 'EMP'.fake()->unique()->numerify('####'),
            'confidence' => fake()->randomFloat(2, 70, 99.99),
            'verify_status' => '1',
            'logged_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'direction' => fake()->randomElement(['in', 'out', 'unknow']),
            'person_name' => fn (array $attributes) => Employee::find($attributes['employee_id'])?->full_name,
            'captured_photo' => null,
            'raw_payload' => null,
        ];
    }

    /**
     * Indicate the log is for an unmatched employee.
     */
    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => null,
            'employee_code' => 'UNKNOWN'.fake()->unique()->numerify('####'),
            'person_name' => fake()->name(),
        ]);
    }

    /**
     * Indicate the log has high confidence.
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 90, 99.99),
        ]);
    }

    /**
     * Indicate the log has low confidence.
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->randomFloat(2, 50, 69.99),
        ]);
    }

    /**
     * Create a clock-in log.
     */
    public function clockIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'in',
        ]);
    }

    /**
     * Create a clock-out log.
     */
    public function clockOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'out',
        ]);
    }
}
