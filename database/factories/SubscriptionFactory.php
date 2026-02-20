<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'default',
            'paymongo_id' => null,
            'paymongo_plan_id' => null,
            'paymongo_status' => SubscriptionStatus::Active,
            'plan_price_id' => PlanPrice::factory(),
            'quantity' => 1,
            'current_period_end' => now()->addMonth(),
            'ends_at' => null,
        ];
    }

    /**
     * Configure an active subscription.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'paymongo_status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonth(),
            'ends_at' => null,
        ]);
    }

    /**
     * Configure a cancelled subscription (on grace period).
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'paymongo_status' => SubscriptionStatus::Active,
            'ends_at' => now()->addDays(15),
        ]);
    }

    /**
     * Configure an expired subscription.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'paymongo_status' => SubscriptionStatus::Cancelled,
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Configure a past due subscription.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'paymongo_status' => SubscriptionStatus::PastDue,
        ]);
    }
}
