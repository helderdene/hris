<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\Admin\AssignPlanRequest;
use App\Http\Requests\Admin\ExtendTrialRequest;
use App\Http\Requests\Admin\StoreCustomPlanRequest;
use App\Http\Requests\Admin\UpdateCustomPlanRequest;
use App\Models\Plan;
use App\Models\PlanModule;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Services\Billing\PayMongoSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlatformAdminController extends Controller
{
    /**
     * Display the admin dashboard with aggregate stats.
     */
    public function dashboard(): Response
    {
        $totalTenants = Tenant::count();
        $activeSubscriptions = Subscription::where('paymongo_status', SubscriptionStatus::Active)
            ->whereNull('ends_at')
            ->count();
        $activeTrials = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
        $expiredTrials = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->count();

        // MRR calculation: sum of active subscription amounts
        $mrr = Subscription::where('paymongo_status', SubscriptionStatus::Active)
            ->whereNull('ends_at')
            ->join('plan_prices', 'subscriptions.plan_price_id', '=', 'plan_prices.id')
            ->selectRaw('SUM(plan_prices.price_per_unit * subscriptions.quantity) as total')
            ->value('total') ?? 0;

        // Trial conversion rate
        $totalTrials = Tenant::whereNotNull('trial_ends_at')->count();
        $convertedTrials = Tenant::whereNotNull('trial_ends_at')
            ->whereHas('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Active))
            ->count();
        $trialConversionRate = $totalTrials > 0 ? round(($convertedTrials / $totalTrials) * 100, 1) : 0;

        // Subscriptions by plan
        $subscriptionsByPlan = Plan::query()
            ->withCount(['tenants' => fn ($q) => $q->whereHas('subscriptions', fn ($sq) => $sq->where('paymongo_status', SubscriptionStatus::Active)->whereNull('ends_at'))])
            ->get()
            ->map(fn (Plan $plan) => [
                'name' => $plan->name,
                'count' => $plan->tenants_count,
            ])
            ->filter(fn ($item) => $item['count'] > 0)
            ->values();

        // Recent registrations
        $recentRegistrations = Tenant::query()
            ->with('plan')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan_name' => $tenant->plan?->name,
                'status' => $this->getTenantStatus($tenant),
                'created_at' => $tenant->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_subscriptions' => $activeSubscriptions,
                'active_trials' => $activeTrials,
                'expired_trials' => $expiredTrials,
                'mrr' => $mrr,
                'trial_conversion_rate' => $trialConversionRate,
            ],
            'subscriptionsByPlan' => $subscriptionsByPlan,
            'recentRegistrations' => $recentRegistrations,
        ]);
    }

    /**
     * Display the paginated tenants list.
     */
    public function tenants(Request $request): Response
    {
        $query = Tenant::query()->with('plan');

        // Search by name or slug
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by plan
        if ($planId = $request->input('plan_id')) {
            $query->where('plan_id', $planId);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            match ($status) {
                'trial' => $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()),
                'active' => $query->whereHas('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Active)->whereNull('ends_at')),
                'expired' => $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<=', now())
                    ->whereDoesntHave('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Active)),
                'cancelled' => $query->whereHas('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Cancelled)),
                'no_subscription' => $query->whereNull('plan_id'),
                default => null,
            };
        }

        // Sort
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSorts = ['name', 'created_at', 'employee_count_cache'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $tenants = $query->paginate(15)->withQueryString();

        // Transform paginated data
        $tenants->through(fn (Tenant $tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan_name' => $tenant->plan?->name,
            'employee_count' => $tenant->employee_count_cache ?? 0,
            'status' => $this->getTenantStatus($tenant),
            'created_at' => $tenant->created_at->toISOString(),
        ]);

        $plans = Plan::select('id', 'name')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => $request->only(['search', 'plan_id', 'status', 'sort', 'direction']),
        ]);
    }

    /**
     * Display detailed tenant information.
     */
    public function showTenant(Tenant $tenant): Response
    {
        $tenant->load(['plan.modules', 'plan.prices', 'subscriptions.planPrice']);

        $adminUsers = $tenant->users()
            ->wherePivot('role', 'admin')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $subscriptionHistory = $tenant->subscriptions()
            ->with('planPrice.plan')
            ->latest()
            ->get()
            ->map(fn (Subscription $sub) => [
                'id' => $sub->id,
                'plan_name' => $sub->planPrice?->plan?->name,
                'status' => $sub->paymongo_status->value,
                'billing_interval' => $sub->planPrice?->billing_interval,
                'price_per_unit' => $sub->planPrice?->price_per_unit,
                'quantity' => $sub->quantity,
                'current_period_end' => $sub->current_period_end?->toISOString(),
                'ends_at' => $sub->ends_at?->toISOString(),
                'created_at' => $sub->created_at->toISOString(),
            ]);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'created_at' => $tenant->created_at->toISOString(),
                'employee_count' => $tenant->employee_count_cache ?? 0,
                'status' => $this->getTenantStatus($tenant),
                'plan' => $tenant->plan ? [
                    'id' => $tenant->plan->id,
                    'name' => $tenant->plan->name,
                    'slug' => $tenant->plan->slug,
                    'modules' => $tenant->plan->modules->map(fn ($m) => [
                        'module' => $m->module,
                        'label' => Module::tryFrom($m->module)?->label() ?? $m->module,
                    ])->values(),
                ] : null,
                'trial_ends_at' => $tenant->trial_ends_at?->toISOString(),
                'is_on_trial' => $tenant->onTrial(),
                'trial_expired' => $tenant->trialExpired(),
            ],
            'subscription' => ($sub = $tenant->subscription('default')) ? [
                'id' => $sub->id,
                'paymongo_status' => $sub->paymongo_status->value,
                'billing_interval' => $sub->planPrice?->billing_interval,
                'price_per_unit' => $sub->planPrice?->price_per_unit,
                'quantity' => $sub->quantity,
                'current_period_end' => $sub->current_period_end?->toISOString(),
                'ends_at' => $sub->ends_at?->toISOString(),
            ] : null,
            'usage' => [
                'employee_count' => $tenant->employee_count_cache ?? 0,
                'max_employees' => $tenant->effectiveLimit('max_employees'),
                'biometric_device_count' => 0,
                'max_biometric_devices' => $tenant->effectiveLimit('max_biometric_devices'),
            ],
            'adminUsers' => $adminUsers,
            'subscriptionHistory' => $subscriptionHistory,
            'plans' => Plan::select('id', 'name')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Extend or grant a trial period for a tenant.
     */
    public function extendTrial(ExtendTrialRequest $request, Tenant $tenant): RedirectResponse
    {
        $days = $request->validated('days');

        $tenant->trial_ends_at = now()->addDays($days);
        $tenant->trial_expired_notified_at = null;

        // Assign Professional plan if tenant has no plan
        if (! $tenant->plan_id) {
            $professionalPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();
            if ($professionalPlan) {
                $tenant->plan_id = $professionalPlan->id;
            }
        }

        $tenant->save();

        return back()->with('success', "Trial extended by {$days} days.");
    }

    /**
     * Override a tenant's plan (sales-assisted, no PayMongo).
     */
    public function assignPlan(AssignPlanRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update(['plan_id' => $request->validated('plan_id')]);

        return back()->with('success', 'Plan assigned successfully.');
    }

    /**
     * Cancel a tenant's active subscription.
     */
    public function cancelSubscription(Tenant $tenant): RedirectResponse
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            return back()->with('error', 'No active subscription to cancel.');
        }

        try {
            app(PayMongoSubscriptionService::class)->cancel($subscription);

            return back()->with('success', 'Subscription cancelled successfully.');
        } catch (\Throwable $e) {
            Log::error('Admin: Cancel subscription failed', ['error' => $e->getMessage(), 'tenant' => $tenant->id]);

            return back()->with('error', 'Unable to cancel subscription. Please try again.');
        }
    }

    /**
     * Impersonate a tenant via cross-domain redirect.
     */
    public function impersonate(Request $request, Tenant $tenant): SymfonyResponse
    {
        $token = TenantRedirectToken::create([
            'user_id' => $request->user()->id,
            'tenant_id' => $tenant->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(5),
        ]);

        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';
        $redirectUrl = "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$token->token}";

        return Inertia::location($redirectUrl);
    }

    /**
     * Display all plans with tenant counts.
     */
    public function plans(): Response
    {
        $plans = Plan::query()
            ->withCount('tenants')
            ->with(['prices', 'modules'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'is_active' => $plan->is_active,
                'is_custom' => $plan->is_custom,
                'tenant_count' => $plan->tenants_count,
                'limits' => $plan->limits,
                'prices' => $plan->prices->map(fn (PlanPrice $p) => [
                    'id' => $p->id,
                    'billing_interval' => $p->billing_interval,
                    'price_per_unit' => $p->price_per_unit,
                    'currency' => $p->currency,
                ])->values(),
                'modules' => $plan->modules->map(fn ($m) => [
                    'module' => $m->module,
                    'label' => Module::tryFrom($m->module)?->label() ?? $m->module,
                ])->values(),
            ]);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Toggle a plan's active status.
     */
    public function togglePlan(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        $status = $plan->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Plan {$status} successfully.");
    }

    /**
     * Show the custom plan creation form.
     */
    public function createCustomPlan(): Response
    {
        $modules = collect(Module::cases())->map(fn (Module $m) => [
            'value' => $m->value,
            'label' => $m->label(),
        ]);

        $tenants = Tenant::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Plans/Create', [
            'modules' => $modules,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Store a new custom plan.
     */
    public function storeCustomPlan(StoreCustomPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $plan = Plan::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'is_custom' => true,
                'tenant_id' => $validated['tenant_id'] ?? null,
                'limits' => $validated['limits'],
                'sort_order' => Plan::max('sort_order') + 1,
            ]);

            foreach ($validated['modules'] as $module) {
                PlanModule::create([
                    'plan_id' => $plan->id,
                    'module' => $module,
                ]);
            }

            foreach ($validated['prices'] as $price) {
                PlanPrice::create([
                    'plan_id' => $plan->id,
                    'billing_interval' => $price['billing_interval'],
                    'price_per_unit' => $price['price_per_unit'],
                    'currency' => config('billing.currency', 'PHP'),
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('admin.plans')->with('success', 'Custom plan created successfully.');
    }

    /**
     * Show the custom plan edit form.
     */
    public function editCustomPlan(Plan $plan): Response
    {
        abort_if(! $plan->is_custom, 403, 'Only custom plans can be edited.');

        $plan->load(['modules', 'prices']);

        $modules = collect(Module::cases())->map(fn (Module $m) => [
            'value' => $m->value,
            'label' => $m->label(),
        ]);

        $tenants = Tenant::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Plans/Edit', [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'tenant_id' => $plan->tenant_id,
                'limits' => $plan->limits,
                'modules' => $plan->modules->pluck('module')->toArray(),
                'prices' => $plan->prices->map(fn (PlanPrice $p) => [
                    'id' => $p->id,
                    'billing_interval' => $p->billing_interval,
                    'price_per_unit' => $p->price_per_unit,
                ])->values(),
            ],
            'modules' => $modules,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Update a custom plan.
     */
    public function updateCustomPlan(UpdateCustomPlanRequest $request, Plan $plan): RedirectResponse
    {
        abort_if(! $plan->is_custom, 403, 'Only custom plans can be edited.');

        $validated = $request->validated();

        DB::transaction(function () use ($plan, $validated) {
            $plan->update([
                'name' => $validated['name'] ?? $plan->name,
                'slug' => isset($validated['name']) ? Str::slug($validated['name']) : $plan->slug,
                'description' => $validated['description'] ?? $plan->description,
                'tenant_id' => $validated['tenant_id'] ?? $plan->tenant_id,
                'limits' => $validated['limits'] ?? $plan->limits,
            ]);

            if (isset($validated['modules'])) {
                $plan->modules()->delete();
                foreach ($validated['modules'] as $module) {
                    PlanModule::create([
                        'plan_id' => $plan->id,
                        'module' => $module,
                    ]);
                }
            }

            if (isset($validated['prices'])) {
                $plan->prices()->delete();
                foreach ($validated['prices'] as $price) {
                    PlanPrice::create([
                        'plan_id' => $plan->id,
                        'billing_interval' => $price['billing_interval'],
                        'price_per_unit' => $price['price_per_unit'],
                        'currency' => config('billing.currency', 'PHP'),
                        'is_active' => true,
                    ]);
                }
            }
        });

        return redirect()->route('admin.plans')->with('success', 'Custom plan updated successfully.');
    }

    /**
     * Determine the display status for a tenant.
     */
    protected function getTenantStatus(Tenant $tenant): string
    {
        if ($tenant->onTrial()) {
            return 'trial';
        }

        if ($tenant->subscribed('default')) {
            return 'active';
        }

        if ($tenant->trialExpired()) {
            return 'expired';
        }

        $cancelled = $tenant->subscriptions()
            ->where('paymongo_status', SubscriptionStatus::Cancelled)
            ->exists();

        if ($cancelled) {
            return 'cancelled';
        }

        return 'none';
    }
}
