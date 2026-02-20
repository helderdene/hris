<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use Paymongo\PaymongoClient;

class PayMongoSubscriptionService
{
    private PayMongoSubscriptionPlanService $planApi;

    private PayMongoSubscriptionApiService $subscriptionApi;

    public function __construct(public PaymongoClient $client)
    {
        $this->planApi = new PayMongoSubscriptionPlanService($client);
        $this->subscriptionApi = new PayMongoSubscriptionApiService($client);
    }

    /**
     * Create a subscription for a tenant.
     *
     * Creates a per-tenant dynamic PayMongo plan with calculated amount,
     * then creates a subscription against that plan.
     */
    public function create(Tenant $tenant, PlanPrice $planPrice, int $quantity): Subscription
    {
        $amount = $this->calculateAmount($planPrice, $quantity);

        $paymongoCustomerService = new PayMongoCustomerService($this->client);
        $customer = $paymongoCustomerService->createOrGet($tenant);

        $paymongoPlan = $this->planApi->create([
            'name' => "Tenant {$tenant->id} - {$planPrice->plan->slug} plan",
            'amount' => $amount,
            'currency' => $planPrice->currency ?? config('billing.currency'),
            'interval' => $planPrice->billing_interval,
        ]);

        $paymongoSubscription = $this->subscriptionApi->create([
            'customer_id' => $customer->id,
            'plan_id' => $paymongoPlan['id'],
            'description' => "Subscription for {$tenant->name}",
        ]);

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'name' => 'default',
            'paymongo_id' => $paymongoSubscription['id'],
            'paymongo_plan_id' => $paymongoPlan['id'],
            'paymongo_status' => SubscriptionStatus::Active,
            'plan_price_id' => $planPrice->id,
            'quantity' => $quantity,
            'current_period_end' => now()->addMonth(),
        ]);
    }

    /**
     * Update subscription quantity (employee count changed).
     *
     * Recalculates amount (price_per_unit * max(count, minimum)) and updates the PayMongo plan.
     */
    public function updateQuantity(Subscription $subscription, int $newQuantity): void
    {
        $planPrice = $subscription->planPrice;
        $baseAmount = $this->calculateAmount($planPrice, $newQuantity);

        $tenant = $subscription->tenant;
        $addonCost = $tenant->activeAddons()->get()->sum(fn ($addon) => $addon->monthlyCost());

        $totalAmount = $baseAmount + $addonCost;

        $this->planApi->update($subscription->paymongo_plan_id, [
            'amount' => $totalAmount,
        ]);

        $subscription->update(['quantity' => $newQuantity]);
    }

    /**
     * Change the subscription to a different plan/tier.
     *
     * Creates a new PayMongo plan for the new tier and updates the subscription.
     */
    public function changePlan(Subscription $subscription, PlanPrice $newPlanPrice): void
    {
        $amount = $this->calculateAmount($newPlanPrice, $subscription->quantity);

        $newPlan = $this->planApi->create([
            'name' => "Tenant {$subscription->tenant_id} - {$newPlanPrice->plan->slug} plan",
            'amount' => $amount,
            'currency' => $newPlanPrice->currency ?? config('billing.currency'),
            'interval' => $newPlanPrice->billing_interval,
        ]);

        $this->subscriptionApi->update($subscription->paymongo_id, [
            'plan_id' => $newPlan['id'],
        ]);

        $subscription->update([
            'paymongo_plan_id' => $newPlan['id'],
            'plan_price_id' => $newPlanPrice->id,
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription): void
    {
        $this->subscriptionApi->cancel($subscription->paymongo_id);

        $subscription->update([
            'paymongo_status' => SubscriptionStatus::Cancelled,
            'ends_at' => $subscription->current_period_end ?? now(),
        ]);
    }

    /**
     * Get a checkout URL for a new subscription.
     *
     * Creates a PayMongo checkout session and returns the redirect URL.
     *
     * @return array{checkout_url: string, paymongo_plan_id: string}
     */
    public function getCheckoutUrl(Tenant $tenant, PlanPrice $planPrice, int $quantity): array
    {
        $amount = $this->calculateAmount($planPrice, $quantity);

        $paymongoPlan = $this->planApi->create([
            'name' => "Tenant {$tenant->id} - {$planPrice->plan->slug} plan",
            'amount' => $amount,
            'currency' => $planPrice->currency ?? config('billing.currency'),
            'interval' => $planPrice->billing_interval,
        ]);

        $paymongoCustomerService = new PayMongoCustomerService($this->client);
        $customer = $paymongoCustomerService->createOrGet($tenant);

        $subscription = $this->subscriptionApi->create([
            'customer_id' => $customer->id,
            'plan_id' => $paymongoPlan['id'],
            'description' => "Subscription for {$tenant->name}",
        ]);

        return [
            'checkout_url' => $subscription['attributes']['checkout_url'] ?? '',
            'paymongo_plan_id' => $paymongoPlan['id'],
        ];
    }

    /**
     * Calculate the subscription amount in centavos.
     *
     * Enforces tier minimums from config('billing.minimum_employees').
     * Amount = price_per_unit * max(quantity, tier_minimum) * 100 (convert to centavos).
     */
    public function calculateAmount(PlanPrice $planPrice, int $quantity): int
    {
        $planSlug = $planPrice->plan->slug;
        $minimums = config('billing.minimum_employees', []);
        $minimum = $minimums[$planSlug] ?? 1;

        $billableQuantity = max($quantity, $minimum);

        return (int) ($planPrice->price_per_unit * $billableQuantity * 100);
    }
}
