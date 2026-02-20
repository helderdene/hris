<?php

namespace Database\Factories;

use App\Enums\Module;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'For growing companies that need recruitment, training, and performance tools.',
            'is_active' => true,
            'is_custom' => false,
            'tenant_id' => null,
            'sort_order' => 1,
            'limits' => [
                'max_employees' => 250,
                'max_admin_users' => 10,
                'max_departments' => -1,
                'max_biometric_devices' => 10,
                'max_kiosks' => 5,
                'storage_gb' => 10,
                'api_access' => 'read_only',
            ],
        ];
    }

    /**
     * Configure the Starter plan.
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For small companies that need essential HR, payroll, and compliance tools.',
            'sort_order' => 0,
            'limits' => [
                'max_employees' => 50,
                'max_admin_users' => 3,
                'max_departments' => 5,
                'max_biometric_devices' => 2,
                'max_kiosks' => 1,
                'storage_gb' => 1,
                'api_access' => false,
            ],
        ])->afterCreating(function (Plan $plan) {
            foreach (Module::starterModules() as $module) {
                $plan->modules()->create(['module' => $module->value]);
            }
        });
    }

    /**
     * Configure the Professional plan.
     */
    public function professional(): static
    {
        return $this->afterCreating(function (Plan $plan) {
            foreach (Module::professionalModules() as $module) {
                $plan->modules()->create(['module' => $module->value]);
            }
        });
    }

    /**
     * Configure the Enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For large organizations needing full compliance, integrations, and employer branding.',
            'sort_order' => 2,
            'limits' => [
                'max_employees' => -1,
                'max_admin_users' => -1,
                'max_departments' => -1,
                'max_biometric_devices' => -1,
                'max_kiosks' => -1,
                'storage_gb' => 100,
                'api_access' => 'full',
            ],
        ])->afterCreating(function (Plan $plan) {
            foreach (Module::enterpriseModules() as $module) {
                $plan->modules()->create(['module' => $module->value]);
            }
        });
    }

    /**
     * Configure a custom plan.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Custom Plan',
            'slug' => 'custom-'.fake()->unique()->numberBetween(1, 9999),
            'is_custom' => true,
        ]);
    }

    /**
     * Configure an inactive plan.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
