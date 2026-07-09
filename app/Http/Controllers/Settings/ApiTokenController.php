<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Show the API token management page.
     */
    public function show(Request $request): Response
    {
        $tenant = tenant();

        return Inertia::render('settings/ApiToken', [
            'hasToken' => $tenant->api_token_hash !== null,
            'lastUsedAt' => $tenant->api_token_last_used_at?->toISOString(),
        ]);
    }

    /**
     * Generate a new API token (replaces any existing token).
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = tenant();
        $plaintext = $tenant->generateApiToken();

        return back()->with('newToken', $plaintext);
    }

    /**
     * Revoke the current API token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $tenant = tenant();
        $tenant->revokeApiToken();

        return back()->with('success', 'API token revoked.');
    }
}
