<?php

namespace App\Http\Responses;

use App\Models\TenantRedirectToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * Handles post-login redirect based on user's tenant membership:
     * - Single tenant: Redirect directly to tenant subdomain with secure token
     * - Multiple tenants: Redirect to tenant selection page
     * - No tenants: Redirect to dashboard (or could show a message)
     */
    public function toResponse($request): Response
    {
        $user = $request->user();
        $tenants = $user->tenants;

        // If user has exactly one tenant, redirect directly to that tenant's subdomain
        if ($tenants->count() === 1) {
            $tenant = $tenants->first();

            return $this->redirectToTenant($request, $tenant);
        }

        // If user has multiple tenants, redirect to tenant selection page
        if ($tenants->count() > 1) {
            return $this->redirectToTenantSelector($request);
        }

        // No tenants - super admins go to admin dashboard, others register
        if ($user->is_super_admin) {
            return $this->redirectToAdmin($request);
        }

        return redirect()->route('tenant.register');
    }

    /**
     * Redirect directly to a tenant's subdomain with a secure token.
     */
    protected function redirectToTenant(Request $request, $tenant): Response
    {
        // Super admins with tenants still go to admin dashboard
        if ($request->user()->is_super_admin) {
            return $this->redirectToAdmin($request);
        }

        // Create secure redirect token
        $token = TenantRedirectToken::create([
            'user_id' => $request->user()->id,
            'tenant_id' => $tenant->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Build the subdomain URL
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';
        $redirectUrl = "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$token->token}";

        // Use Inertia::location() to force full page redirect (avoids CORS issues with subdomain)
        return Inertia::location($redirectUrl);
    }

    /**
     * Redirect to the tenant selection page.
     */
    protected function redirectToTenantSelector(Request $request): Response
    {
        // Super admins with multiple tenants go to admin dashboard
        if ($request->user()->is_super_admin) {
            return $this->redirectToAdmin($request);
        }

        return redirect()->intended('/select-tenant');
    }

    /**
     * Redirect to the platform admin dashboard.
     */
    protected function redirectToAdmin(Request $request): Response
    {
        return redirect()->intended('/admin');
    }
}
