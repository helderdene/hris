<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantSelectorController extends Controller
{
    /**
     * Display the tenant selection page.
     *
     * Shows all tenants the authenticated user has access to,
     * including human-readable role labels for each tenant.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tenants = $user->tenants()
            ->select(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo_path', 'tenants.primary_color'])
            ->orderBy('tenants.name')
            ->get()
            ->map(function (Tenant $tenant) {
                $role = $tenant->pivot->role;

                // Get the role label from the enum
                $roleLabel = $role instanceof TenantUserRole
                    ? $role->label()
                    : (TenantUserRole::tryFrom($role)?->label() ?? 'Member');

                // Get the role value as a string for frontend styling
                $roleValue = $role instanceof TenantUserRole
                    ? $role->value
                    : $role;

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo_path' => $tenant->logo_path,
                    'primary_color' => $tenant->primary_color,
                    'role' => $roleValue,
                    'role_label' => $roleLabel,
                ];
            });

        return Inertia::render('TenantSelector', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Handle tenant selection and redirect to the tenant subdomain.
     *
     * Validates that the user is a member of the selected tenant,
     * creates a secure redirect token, and redirects to the subdomain.
     */
    public function select(Request $request, Tenant $tenant): SymfonyResponse
    {
        $user = $request->user();

        // Verify user has membership in this tenant
        $isMember = $user->tenants()->where('tenants.id', $tenant->id)->exists();

        if (! $isMember) {
            abort(403, 'You do not have access to this organization.');
        }

        // Generate secure redirect token
        $token = $this->createRedirectToken($user->id, $tenant->id);

        // Build the subdomain URL with the token
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';
        $redirectUrl = "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$token->token}";

        // Use Inertia::location() to force full page redirect (avoids CORS issues with subdomain)
        return Inertia::location($redirectUrl);
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
