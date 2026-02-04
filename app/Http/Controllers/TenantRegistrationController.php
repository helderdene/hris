<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRegistrationRequest;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\HelpContentSeeder;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantRegistrationController extends Controller
{
    public function __construct(
        protected TenantDatabaseManager $tenantDatabaseManager
    ) {}

    /**
     * Display the tenant registration form.
     */
    public function create(): Response
    {
        return Inertia::render('TenantRegistration', [
            'mainDomain' => config('app.main_domain', 'kasamahr.test'),
        ]);
    }

    /**
     * Handle tenant registration and provision the tenant schema.
     */
    public function store(TenantRegistrationRequest $request): SymfonyResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Create the tenant record in platform schema
        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'business_info' => $validated['business_info'],
            'timezone' => 'Asia/Manila',
        ]);

        // Provision the tenant database schema
        $this->tenantDatabaseManager->createSchema($tenant);
        $this->tenantDatabaseManager->migrateSchema($tenant);

        // Seed initial help content
        (new HelpContentSeeder)->run();

        // Add current user as tenant admin
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        // Generate secure redirect token for cross-subdomain authentication
        $token = $this->createRedirectToken($user->id, $tenant->id);

        // Build subdomain redirect URL with success indicator
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = $request->secure() ? 'https' : 'http';
        $redirectUrl = "{$scheme}://{$tenant->slug}.{$mainDomain}/?token={$token->token}&created=1";

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
