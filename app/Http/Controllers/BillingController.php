<?php

namespace App\Http\Controllers;

use App\Enums\AddonType;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Billing\PurchaseAddonRequest;
use App\Http\Requests\Billing\UpdateAddonRequest;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\TenantAddon;
use App\Services\Billing\PayMongoCustomerService;
use App\Services\Billing\PayMongoSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function __construct(
        protected PayMongoSubscriptionService $subscriptionService,
        protected PayMongoCustomerService $customerService
    ) {}

    /**
     * Display the billing dashboard.
     */
    public function index(): Response
    {
        $tenant = app('tenant');
        $tenant->load(['plan.modules', 'plan.prices']);
        $subscription = $tenant->subscription('default');

        return Inertia::render('Billing/Index', [
            'currentPlan' => $tenant->plan ? [
                'id' => $tenant->plan->id,
                'name' => $tenant->plan->name,
                'slug' => $tenant->plan->slug,
                'description' => $tenant->plan->description,
                'limits' => $tenant->plan->limits,
                'modules' => $tenant->plan->modules->map(fn ($m) => [
                    'module' => $m->module,
                    'label' => \App\Enums\Module::tryFrom($m->module)?->label() ?? $m->module,
                ])->values(),
            ] : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'paymongo_status' => $subscription->paymongo_status->value,
                'plan_price_id' => $subscription->plan_price_id,
                'quantity' => $subscription->quantity,
                'current_period_end' => $subscription->current_period_end?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'plan_price' => $subscription->planPrice ? [
                    'id' => $subscription->planPrice->id,
                    'billing_interval' => $subscription->planPrice->billing_interval,
                    'price_per_unit' => $subscription->planPrice->price_per_unit,
                    'currency' => $subscription->planPrice->currency,
                ] : null,
            ] : null,
            'usage' => [
                'employee_count' => $tenant->employee_count_cache ?? 0,
                'max_employees' => $tenant->effectiveLimit('max_employees'),
                'biometric_device_count' => 0,
                'max_biometric_devices' => $tenant->effectiveLimit('max_biometric_devices'),
            ],
            'addons' => $tenant->activeAddons->map(fn (TenantAddon $addon) => [
                'id' => $addon->id,
                'type' => $addon->type->value,
                'type_label' => $addon->type->label(),
                'quantity' => $addon->quantity,
                'price_per_unit' => $addon->price_per_unit,
                'is_active' => $addon->is_active,
                'extra_units' => $addon->extraUnits(),
                'monthly_cost' => $addon->monthlyCost(),
            ])->values(),
            'isOnTrial' => $tenant->onTrial(),
            'trialEndsAt' => $tenant->trial_ends_at?->toISOString(),
        ]);
    }

    /**
     * Display plan comparison page.
     */
    public function plans(): Response
    {
        $tenant = app('tenant');

        $plans = Plan::query()
            ->active()
            ->standard()
            ->with(['prices' => fn ($q) => $q->where('is_active', true), 'modules'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'limits' => $plan->limits,
                'sort_order' => $plan->sort_order,
                'prices' => $plan->prices->map(fn ($p) => [
                    'id' => $p->id,
                    'billing_interval' => $p->billing_interval,
                    'price_per_unit' => $p->price_per_unit,
                    'currency' => $p->currency,
                ])->values(),
                'modules' => $plan->modules->map(fn ($m) => [
                    'module' => $m->module,
                    'label' => \App\Enums\Module::tryFrom($m->module)?->label() ?? $m->module,
                ])->values(),
            ]);

        return Inertia::render('Billing/Plans', [
            'plans' => $plans,
            'currentPlanSlug' => $tenant->plan?->slug,
            'hasActiveSubscription' => $tenant->subscribed('default'),
        ]);
    }

    /**
     * Display the upgrade prompt page (when module is locked).
     */
    public function upgrade(): Response
    {
        $tenant = app('tenant');
        $module = request()->query('module');

        $plans = Plan::query()
            ->active()
            ->standard()
            ->with(['prices' => fn ($q) => $q->where('is_active', true), 'modules'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'limits' => $plan->limits,
                'sort_order' => $plan->sort_order,
                'prices' => $plan->prices->map(fn ($p) => [
                    'id' => $p->id,
                    'billing_interval' => $p->billing_interval,
                    'price_per_unit' => $p->price_per_unit,
                    'currency' => $p->currency,
                ])->values(),
                'modules' => $plan->modules->map(fn ($m) => [
                    'module' => $m->module,
                    'label' => \App\Enums\Module::tryFrom($m->module)?->label() ?? $m->module,
                ])->values(),
            ]);

        return Inertia::render('Billing/Upgrade', [
            'plans' => $plans,
            'lockedModule' => $module,
            'lockedModuleLabel' => $module ? (\App\Enums\Module::tryFrom($module)?->label() ?? $module) : null,
            'currentPlanSlug' => $tenant->plan?->slug,
        ]);
    }

    /**
     * Display the add-ons management page.
     */
    public function addons(): Response
    {
        $tenant = app('tenant');

        $addonTypes = collect(AddonType::cases())->map(fn (AddonType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'units_per_quantity' => $type->unitsPerQuantity(),
            'default_price' => $type->defaultPrice(),
        ]);

        return Inertia::render('Billing/Addons', [
            'addons' => $tenant->activeAddons->map(fn (TenantAddon $addon) => [
                'id' => $addon->id,
                'type' => $addon->type->value,
                'type_label' => $addon->type->label(),
                'quantity' => $addon->quantity,
                'price_per_unit' => $addon->price_per_unit,
                'is_active' => $addon->is_active,
                'extra_units' => $addon->extraUnits(),
                'monthly_cost' => $addon->monthlyCost(),
            ])->values(),
            'addonTypes' => $addonTypes,
            'effectiveLimits' => [
                'max_employees' => $tenant->effectiveLimit('max_employees'),
                'max_biometric_devices' => $tenant->effectiveLimit('max_biometric_devices'),
            ],
            'isEnterprise' => $tenant->plan?->slug === 'enterprise',
        ]);
    }

    /**
     * Display the post-checkout success page.
     */
    public function success(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Billing/Success', [
            'planName' => $tenant->plan?->name ?? 'your plan',
        ]);
    }

    /**
     * Subscribe to a plan via PayMongo checkout.
     */
    public function subscribe(PlanPrice $planPrice): SymfonyResponse
    {
        Gate::authorize('tenant-admin');

        $tenant = app('tenant');

        try {
            $result = $this->subscriptionService->getCheckoutUrl(
                $tenant,
                $planPrice,
                max($tenant->employee_count_cache ?? 0, 1)
            );

            // Create local subscription record with incomplete status
            \App\Models\Subscription::create([
                'tenant_id' => $tenant->id,
                'name' => 'default',
                'paymongo_id' => '',
                'paymongo_plan_id' => $result['paymongo_plan_id'],
                'paymongo_status' => SubscriptionStatus::Incomplete,
                'plan_price_id' => $planPrice->id,
                'quantity' => max($tenant->employee_count_cache ?? 0, 1),
                'current_period_end' => now()->addMonth(),
            ]);

            $tenant->update(['plan_id' => $planPrice->plan_id]);

            return Inertia::location($result['checkout_url']);
        } catch (\Throwable $e) {
            Log::error('Billing: Subscribe failed', ['error' => $e->getMessage(), 'tenant' => $tenant->id]);

            return back()->with('error', 'Unable to start checkout. Please try again.');
        }
    }

    /**
     * Change the current subscription to a different plan.
     */
    public function changePlan(PlanPrice $newPlanPrice): RedirectResponse
    {
        Gate::authorize('tenant-admin');

        $tenant = app('tenant');
        $subscription = $tenant->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            return back()->with('error', 'No active subscription to change.');
        }

        try {
            $this->subscriptionService->changePlan($subscription, $newPlanPrice);
            $tenant->update(['plan_id' => $newPlanPrice->plan_id]);

            return back()->with('success', 'Plan changed successfully.');
        } catch (\Throwable $e) {
            Log::error('Billing: Change plan failed', ['error' => $e->getMessage(), 'tenant' => $tenant->id]);

            return back()->with('error', 'Unable to change plan. Please try again.');
        }
    }

    /**
     * Cancel the current subscription.
     */
    public function cancel(): RedirectResponse
    {
        Gate::authorize('tenant-admin');

        $tenant = app('tenant');
        $subscription = $tenant->subscription('default');

        if (! $subscription) {
            return back()->with('error', 'No subscription to cancel.');
        }

        try {
            $this->subscriptionService->cancel($subscription);

            return back()->with('success', 'Subscription cancelled. Access continues until the end of your billing period.');
        } catch (\Throwable $e) {
            Log::error('Billing: Cancel failed', ['error' => $e->getMessage(), 'tenant' => $tenant->id]);

            return back()->with('error', 'Unable to cancel subscription. Please try again.');
        }
    }

    /**
     * Purchase a new add-on.
     */
    public function purchaseAddon(PurchaseAddonRequest $request): RedirectResponse
    {
        Gate::authorize('tenant-admin');

        $tenant = app('tenant');
        $validated = $request->validated();

        $addonType = AddonType::from($validated['type']);

        TenantAddon::create([
            'tenant_id' => $tenant->id,
            'type' => $addonType->value,
            'quantity' => $validated['quantity'],
            'price_per_unit' => $addonType->defaultPrice(),
            'currency' => config('billing.currency'),
            'is_active' => true,
        ]);

        // Recalculate subscription amount if active
        $this->recalculateSubscriptionAmount($tenant);

        return back()->with('success', "{$addonType->label()} purchased successfully.");
    }

    /**
     * Update an existing add-on's quantity.
     */
    public function updateAddon(UpdateAddonRequest $request, TenantAddon $tenantAddon): RedirectResponse
    {
        Gate::authorize('tenant-admin');

        $tenantAddon->update(['quantity' => $request->validated('quantity')]);

        $tenant = app('tenant');
        $this->recalculateSubscriptionAmount($tenant);

        return back()->with('success', 'Add-on updated successfully.');
    }

    /**
     * Cancel (deactivate) an add-on.
     */
    public function cancelAddon(TenantAddon $tenantAddon): RedirectResponse
    {
        Gate::authorize('tenant-admin');

        $tenantAddon->update(['is_active' => false]);

        $tenant = app('tenant');
        $this->recalculateSubscriptionAmount($tenant);

        return back()->with('success', 'Add-on cancelled successfully.');
    }

    /**
     * Recalculate subscription amount after add-on changes.
     */
    private function recalculateSubscriptionAmount(\App\Models\Tenant $tenant): void
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            return;
        }

        try {
            $this->subscriptionService->updateQuantity(
                $subscription,
                max($tenant->employee_count_cache ?? 0, 1)
            );
        } catch (\Throwable $e) {
            Log::error('Billing: Recalculate amount failed', ['error' => $e->getMessage(), 'tenant' => $tenant->id]);
        }
    }
}
