<?php

use App\Http\Middleware\AuthenticateFromMainDomain;
use App\Http\Middleware\AuthenticateFromToken;
use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePasswordConfirmation;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SwitchTenantDatabase;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Register platform routes for main domain
            // Fortify authentication routes are registered on the main domain via config/fortify.php
            Route::middleware('web')
                ->domain(config('app.main_domain', 'kasamahr.test'))
                ->group(base_path('routes/platform.php'));

            // Register tenant routes for subdomains
            // These routes include token-based authentication and tenant membership verification
            Route::middleware(['web', 'tenant'])
                ->domain('{tenant}.'.config('app.main_domain', 'kasamahr.test'))
                ->group(base_path('routes/tenant.php'));

            // Register broadcasting routes for tenant subdomains
            // Required for WebSocket channel authorization on tenant subdomains
            Illuminate\Support\Facades\Broadcast::routes([
                'middleware' => ['web', 'tenant'],
                'domain' => '{tenant}.'.config('app.main_domain', 'kasamahr.test'),
            ]);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Define tenant middleware group for subdomain routes
        // Order matters: ResolveTenant must run first to bind tenant to container
        // AuthenticateFromToken handles cross-subdomain auth via secure tokens
        // SwitchTenantDatabase configures the database connection
        // AuthenticateFromMainDomain verifies user has access to the tenant (session-based)
        // EnsureTenantMember provides an additional authorization gate check
        $middleware->appendToGroup('tenant', [
            ResolveTenant::class,
            AuthenticateFromToken::class,
            SwitchTenantDatabase::class,
            AuthenticateFromMainDomain::class,
            EnsureTenantMember::class,
        ]);

        // Register middleware aliases for role-based access control
        // Usage in routes: ->middleware('ensure-role:admin,hr_manager')
        $middleware->alias([
            'ensure-role' => EnsureRole::class,
            'subscribed' => EnsureActiveSubscription::class,
            'module' => EnsureModuleAccess::class,
            'tenant.password.confirm' => RequirePasswordConfirmation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
