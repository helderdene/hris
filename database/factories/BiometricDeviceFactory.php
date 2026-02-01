<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\BiometricDevice;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BiometricDevice>
 */
class BiometricDeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = BiometricDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Device',
            'device_identifier' => fake()->unique()->regexify('DEV-[A-Z0-9]{8}'),
            'work_location_id' => WorkLocation::factory(),
            'status' => DeviceStatus::Offline,
            'last_seen_at' => null,
            'connection_started_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the device is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Online,
            'last_seen_at' => now(),
            'connection_started_at' => now()->subMinutes(fake()->numberBetween(5, 1440)),
        ]);
    }

    /**
     * Indicate that the device is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::Offline,
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(30, 1440)),
            'connection_started_at' => null,
        ]);
    }

    /**
     * Indicate that the device is inactive (disabled).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the device is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Associate the device with a specific work location.
     */
    public function forWorkLocation(WorkLocation $workLocation): static
    {
        return $this->state(fn (array $attributes) => [
            'work_location_id' => $workLocation->id,
        ]);
    }

    /**
     * Set a specific device identifier.
     */
    public function withIdentifier(string $identifier): static
    {
        return $this->state(fn (array $attributes) => [
            'device_identifier' => $identifier,
        ]);
    }

    /**
     * Set the connection started at timestamp.
     */
    public function connectedAt(\DateTimeInterface $timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_started_at' => $timestamp,
            'status' => DeviceStatus::Online,
            'last_seen_at' => now(),
        ]);
    }
}
