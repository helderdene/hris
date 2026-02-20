<?php

namespace Database\Factories;

use App\Models\Kiosk;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Kiosk>
 */
class KioskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Kiosk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Kiosk',
            'token' => Str::random(64),
            'location' => fake()->optional()->sentence(),
            'work_location_id' => WorkLocation::factory(),
            'ip_whitelist' => null,
            'settings' => ['cooldown_minutes' => 5],
            'is_active' => true,
            'last_activity_at' => null,
        ];
    }

    /**
     * Indicate that the kiosk is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the kiosk is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Associate the kiosk with a specific work location.
     */
    public function forWorkLocation(WorkLocation $workLocation): static
    {
        return $this->state(fn (array $attributes) => [
            'work_location_id' => $workLocation->id,
        ]);
    }

    /**
     * Set an IP whitelist for the kiosk.
     */
    public function withIpWhitelist(array $whitelist = ['192.168.1.0/24']): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_whitelist' => $whitelist,
        ]);
    }
}
