<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitationAction;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\TenantRedirectToken;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvitationController extends Controller
{
    /**
     * Display the set password form for an invitation.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $tenantUser = TenantUser::where('invitation_token', $token)
            ->with(['user', 'tenant'])
            ->first();

        if ($tenantUser === null) {
            return redirect()->route('login')
                ->with('error', 'This invitation link is invalid.');
        }

        if ($tenantUser->isInvitationExpired()) {
            return redirect()->route('login')
                ->with('error', 'This invitation link has expired. Please contact your administrator for a new invitation.');
        }

        return Inertia::render('auth/AcceptInvitation', [
            'user' => [
                'name' => $tenantUser->user->name,
                'email' => $tenantUser->user->email,
            ],
            'tenant' => [
                'name' => $tenantUser->tenant->name,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Accept the invitation and set the user's password.
     */
    public function accept(AcceptInvitationRequest $request, string $token): SymfonyResponse
    {
        // Get tenant info before the action clears the token
        $tenantUser = TenantUser::where('invitation_token', $token)
            ->with('tenant')
            ->first();

        if ($tenantUser === null) {
            return redirect()->route('login')
                ->with('error', 'This invitation link is invalid.');
        }

        $tenant = $tenantUser->tenant;

        $action = new AcceptInvitationAction;

        $user = $action->execute(
            token: $token,
            password: $request->validated('password')
        );

        // Log the user in
        Auth::login($user);

        // Create redirect token for the tenant subdomain
        $redirectToken = TenantRedirectToken::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Build the subdomain URL with the token
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';
        $redirectUrl = "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$redirectToken->token}";

        // Use Inertia::location() to force full page redirect
        return Inertia::location($redirectUrl);
    }
}
