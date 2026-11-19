<?php

namespace App\Http\Controllers;

use App\Enums\TenantProvisioningStatus;
use App\Http\Requests\TenantRegistrationRequest;
use App\Jobs\ProvisionTenantJob;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantRegistrationController extends Controller
{
    /**
     * Display the tenant registration form.
     *
     * While the user has an organization being provisioned the page shows a
     * loading screen and polls this action; it receives the subdomain redirect
     * URL once the user has been attached, or the failed flag to offer a retry.
     * The slug is kept in the URL so polling still knows it after provisioning ends.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $unfinishedTenant = $this->unfinishedTenantFor($request);

        if ($unfinishedTenant !== null && $request->query('provisioning') !== $unfinishedTenant->slug) {
            return to_route('tenant.register', ['provisioning' => $unfinishedTenant->slug]);
        }

        $provisioningSlug = $unfinishedTenant?->slug ?? $request->query('provisioning');
        $provisioningSlug = is_string($provisioningSlug) ? $provisioningSlug : null;

        return Inertia::render('TenantRegistration', [
            'mainDomain' => config('app.main_domain', 'kasamahr.test'),
            'provisioningSlug' => $provisioningSlug,
            'provisioningFailed' => $unfinishedTenant?->provisioning_status === TenantProvisioningStatus::Failed,
            'provisionedRedirectUrl' => $provisioningSlug === null || $unfinishedTenant !== null
                ? null
                : $this->provisionedRedirectUrl($request, $provisioningSlug),
        ]);
    }

    /**
     * Register the tenant and provision its schema in the background.
     *
     * The empty database is created here so a tenant row never exists without
     * one; commands that loop over every tenant, such as tenant:migrate during
     * a deploy, fail on a missing database.
     */
    public function store(TenantRegistrationRequest $request, TenantDatabaseManager $databaseManager): RedirectResponse
    {
        $validated = $request->validated();
        $trialPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'business_info' => $validated['business_info'],
            'timezone' => 'Asia/Manila',
            'provisioning_status' => TenantProvisioningStatus::Pending,
            'provisioning_user_id' => $request->user()->id,
        ]);

        $databaseManager->createSchema($tenant);

        if ($trialPlan) {
            $tenant->update([
                'plan_id' => $trialPlan->id,
                'trial_ends_at' => now()->addDays(config('billing.trial_days', 14)),
            ]);
        }

        ProvisionTenantJob::dispatch($tenant->id, $request->user()->id);

        return to_route('tenant.register', ['provisioning' => $tenant->slug]);
    }

    /**
     * Queue another provisioning attempt for the user's failed organization.
     */
    public function retry(Request $request): RedirectResponse
    {
        $tenant = $this->unfinishedTenantFor($request);

        if ($tenant?->provisioning_status === TenantProvisioningStatus::Failed) {
            $tenant->update(['provisioning_status' => TenantProvisioningStatus::Pending]);

            ProvisionTenantJob::dispatch($tenant->id, $request->user()->id);
        }

        return to_route('tenant.register');
    }

    /**
     * Get the organization this user registered that has not finished provisioning.
     */
    protected function unfinishedTenantFor(Request $request): ?Tenant
    {
        return Tenant::query()
            ->where('provisioning_user_id', $request->user()->id)
            ->whereNotNull('provisioning_status')
            ->latest('id')
            ->first();
    }

    /**
     * Build the subdomain redirect URL once the user belongs to the provisioned tenant.
     */
    protected function provisionedRedirectUrl(Request $request, string $slug): ?string
    {
        $tenant = $request->user()->tenants()->where('slug', $slug)->first();

        if ($tenant === null) {
            return null;
        }

        $token = $this->createRedirectToken($request->user()->id, $tenant->id);
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$token->token}&created=1";
    }

    /**
     * Create a secure redirect token for cross-subdomain authentication.
     */
    protected function createRedirectToken(int $userId, int $tenantId): TenantRedirectToken
    {
        return TenantRedirectToken::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(5),
        ]);
    }
}
