<?php

namespace Database\Factories;

use App\Enums\AddonType;
use App\Models\Tenant;
use App\Models\TenantAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TenantAddon>
 */
class TenantAddonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TenantAddon::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AddonType::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'type' => $type,
            'quantity' => 1,
            'price_per_unit' => $type->defaultPrice(),
            'currency' => 'PHP',
            'is_active' => true,
            'expires_at' => null,
        ];
    }

    /**
     * Configure an employee slots add-on.
     */
    public function employeeSlots(int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::EmployeeSlots,
            'quantity' => $quantity,
            'price_per_unit' => AddonType::EmployeeSlots->defaultPrice(),
        ]);
    }

    /**
     * Configure a biometric devices add-on.
     */
    public function biometricDevices(int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AddonType::BiometricDevices,
            'quantity' => $quantity,
            'price_per_unit' => AddonType::BiometricDevices->defaultPrice(),
        ]);
    }

    /**
     * Configure an inactive add-on.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Configure an expired add-on.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
