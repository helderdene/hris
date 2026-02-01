<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkLocation>
 */
class WorkLocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = WorkLocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Office',
            'code' => fake()->unique()->regexify('[A-Z]{2,4}-[0-9]{2}'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country' => fake()->countryCode(),
            'postal_code' => fake()->postcode(),
            'location_type' => fake()->randomElement(LocationType::cases()),
            'timezone' => fake()->timezone(),
            'metadata' => null,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the location is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the location is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a headquarters location.
     */
    public function headquarters(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::Headquarters,
            'name' => 'Main Headquarters',
        ]);
    }

    /**
     * Create a branch location.
     */
    public function branch(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::Branch,
        ]);
    }

    /**
     * Create a satellite office location.
     */
    public function satelliteOffice(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::SatelliteOffice,
        ]);
    }

    /**
     * Create a remote hub location.
     */
    public function remoteHub(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::RemoteHub,
        ]);
    }

    /**
     * Create a warehouse location.
     */
    public function warehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::Warehouse,
        ]);
    }

    /**
     * Create a factory location.
     */
    public function factory(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_type' => LocationType::Factory,
        ]);
    }

    /**
     * Add metadata to the location.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Add common metadata (phone, email, capacity) to the location.
     */
    public function withCommonMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'phone' => fake()->phoneNumber(),
                'email' => fake()->companyEmail(),
                'capacity' => fake()->numberBetween(10, 500),
            ],
        ]);
    }
}
