<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRegistrationRequest;
use App\Jobs\ProvisionTenantJob;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
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
     * While an organization is being provisioned the page polls this action,
     * and receives the subdomain redirect URL once the user has been attached.
     */
    public function create(Request $request): Response
    {
        $provisioningSlug = $request->query('provisioning');
        $provisioningSlug = is_string($provisioningSlug) ? $provisioningSlug : null;

        return Inertia::render('TenantRegistration', [
            'mainDomain' => config('app.main_domain', 'kasamahr.test'),
            'provisioningSlug' => $provisioningSlug,
            'provisionedRedirectUrl' => $provisioningSlug === null
                ? null
                : $this->provisionedRedirectUrl($request, $provisioningSlug),
        ]);
    }

    /**
     * Register the tenant and provision its schema in the background.
     */
    public function store(TenantRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $trialPlan = Plan::where('slug', config('billing.trial_plan', 'professional'))->first();

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'business_info' => $validated['business_info'],
            'timezone' => 'Asia/Manila',
        ]);

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
