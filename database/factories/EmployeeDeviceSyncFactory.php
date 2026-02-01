<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeDeviceSync>
 */
class EmployeeDeviceSyncFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeDeviceSync::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'biometric_device_id' => BiometricDevice::factory(),
            'status' => SyncStatus::Pending,
            'last_synced_at' => null,
            'last_attempted_at' => null,
            'last_error' => null,
            'retry_count' => 0,
            'last_message_id' => null,
        ];
    }

    /**
     * Indicate that the sync status is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Pending,
            'last_synced_at' => null,
            'last_error' => null,
        ]);
    }

    /**
     * Indicate that the sync status is syncing.
     */
    public function syncing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Syncing,
            'last_attempted_at' => now(),
            'last_message_id' => fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the sync status is synced.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Synced,
            'last_synced_at' => now(),
            'last_attempted_at' => now(),
            'last_error' => null,
            'last_message_id' => fake()->uuid(),
        ]);
    }

    /**
     * Indicate that the sync status is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Failed,
            'last_attempted_at' => now(),
            'last_error' => 'Connection timeout',
            'retry_count' => 1,
        ]);
    }

    /**
     * Indicate that the sync has failed multiple times.
     */
    public function failedMultipleTimes(int $retryCount = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Failed,
            'last_attempted_at' => now(),
            'last_error' => 'Connection timeout after multiple retries',
            'retry_count' => $retryCount,
        ]);
    }

    /**
     * Associate with a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Associate with a specific device.
     */
    public function forDevice(BiometricDevice $device): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_device_id' => $device->id,
        ]);
    }
}
