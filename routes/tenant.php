<?php

/**
 * Tenant Routes
 *
 * Routes defined here are scoped to tenant subdomains (e.g., acme.kasamahr.test).
 * The ResolveTenant and SwitchTenantDatabase middleware will be applied to these routes.
 *
 * Note: Tenant context is shared via HandleInertiaRequests middleware,
 * so routes don't need to pass tenant data explicitly.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Kiosk Terminal Routes (No Authentication Required)
|--------------------------------------------------------------------------
| Kiosks are accessed via a unique token URL. No auth/subscription
| middleware is needed — the token itself serves as authentication.
*/
Route::prefix('kiosk')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\KioskTerminalController::class, 'show'])
        ->name('kiosk.show');
    Route::post('/{token}/verify-pin', [\App\Http\Controllers\KioskTerminalController::class, 'verifyPin'])
        ->name('kiosk.verify-pin');
    Route::post('/{token}/clock', [\App\Http\Controllers\KioskTerminalController::class, 'clock'])
        ->name('kiosk.clock');
    Route::post('/{token}/visitor-check-in', [\App\Http\Controllers\KioskTerminalController::class, 'visitorCheckIn'])
        ->name('kiosk.visitor-check-in');
    Route::post('/{token}/visitor-check-out', [\App\Http\Controllers\KioskTerminalController::class, 'visitorCheckOut'])
        ->name('kiosk.visitor-check-out');
});

/*
|--------------------------------------------------------------------------
| Public Visitor Registration Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('visit')->group(function () {
    Route::get('/register', [\App\Http\Controllers\VisitorRegistrationController::class, 'show'])
        ->name('visitor.register');
    Route::post('/register', [\App\Http\Controllers\VisitorRegistrationController::class, 'store'])
        ->name('visitor.register.store');
    Route::get('/search-employees', [\App\Http\Controllers\VisitorRegistrationController::class, 'searchEmployees'])
        ->name('visitor.search-employees');
});

/*
|--------------------------------------------------------------------------
| Public Careers Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::get('/careers', [\App\Http\Controllers\CareersController::class, 'index'])
    ->name('careers.index');

// Public Offer Response (signed URL, no auth required)
Route::get('/offers/{offer}/respond', [\App\Http\Controllers\Recruitment\OfferResponseController::class, 'show'])
    ->name('offers.respond')
    ->middleware('signed');
Route::get('/careers/{slug}', [\App\Http\Controllers\CareersController::class, 'show'])
    ->name('careers.show');
Route::post('/careers/{slug}/apply', [\App\Http\Controllers\Api\PublicApplicationController::class, 'store'])
    ->name('careers.apply');

Route::get('/', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $tenant = app('tenant');
        if ($tenant) {
            $role = $user->getRoleInTenant($tenant);
            if ($role === \App\Enums\TenantUserRole::Employee) {
                return redirect('/my/dashboard');
            }
        }
    }

    return Inertia::render('TenantDashboard', [
        'justCreated' => $request->boolean('created'),
    ]);
})->middleware(['auth', 'verified'])->name('tenant.home');

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $tenant = app('tenant');
        if ($tenant) {
            $role = $user->getRoleInTenant($tenant);
            if ($role === \App\Enums\TenantUserRole::Employee) {
                return redirect('/my/dashboard');
            }
        }
    }

    // Use the ActionCenterDashboardController for HR roles
    return app(\App\Http\Controllers\ActionCenterDashboardController::class)($request);
})->middleware(['auth', 'verified'])->name('tenant.dashboard');

// Logout route for tenant subdomains
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Redirect to main domain welcome page
    $mainDomain = config('app.main_domain', 'kasamahr.test');
    $scheme = $request->secure() ? 'https' : 'http';

    return Inertia::location("{$scheme}://{$mainDomain}/");
})->name('tenant.logout');

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
|
| These routes are for tenant-specific pages and require authentication.
|
*/

/*
|--------------------------------------------------------------------------
| Billing Routes (exempt from subscription check)
|--------------------------------------------------------------------------
|
| These routes must be accessible even when the tenant's subscription
| is expired, so they are NOT wrapped with the 'subscribed' middleware.
|
*/
Route::middleware(['auth'])->prefix('billing')->group(function () {
    // Read-only billing pages (all authenticated users)
    Route::get('/', [\App\Http\Controllers\BillingController::class, 'index'])->name('tenant.billing.index');
    Route::get('/plans', [\App\Http\Controllers\BillingController::class, 'plans'])->name('tenant.billing.plans');
    Route::get('/upgrade', [\App\Http\Controllers\BillingController::class, 'upgrade'])->name('tenant.billing.upgrade');
    Route::get('/success', [\App\Http\Controllers\BillingController::class, 'success'])->name('tenant.billing.success');
    Route::get('/addons', [\App\Http\Controllers\BillingController::class, 'addons'])->name('tenant.billing.addons');

    // Write actions (admin only — gate checked in controller)
    Route::post('/subscribe/{planPrice}', [\App\Http\Controllers\BillingController::class, 'subscribe'])->name('tenant.billing.subscribe');
    Route::post('/change-plan/{planPrice}', [\App\Http\Controllers\BillingController::class, 'changePlan'])->name('tenant.billing.change-plan');
    Route::post('/cancel', [\App\Http\Controllers\BillingController::class, 'cancel'])->name('tenant.billing.cancel');
    Route::post('/addons/purchase', [\App\Http\Controllers\BillingController::class, 'purchaseAddon'])->name('tenant.billing.addons.purchase');
    Route::post('/addons/{tenantAddon}/update', [\App\Http\Controllers\BillingController::class, 'updateAddon'])->name('tenant.billing.addons.update');
    Route::post('/addons/{tenantAddon}/cancel', [\App\Http\Controllers\BillingController::class, 'cancelAddon'])->name('tenant.billing.addons.cancel');
});

Route::middleware(['auth', 'subscribed'])->group(function () {
    // Always available (starter modules) — no module middleware
    require __DIR__.'/tenant/web-core.php';
    require __DIR__.'/tenant/web-employees.php';
    require __DIR__.'/tenant/web-organization.php';
    require __DIR__.'/tenant/web-modules.php';
    require __DIR__.'/tenant/web-self-service.php';

    // Module-gated routes
    Route::middleware(['module:visitor_management'])->group(function () {
        require __DIR__.'/tenant/web-visitors.php';
    });

    Route::middleware(['module:recruitment,onboarding_preboarding'])->group(function () {
        require __DIR__.'/tenant/web-recruitment.php';
    });
});

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| These API routes are for tenant-specific operations and require
| authentication. The tenant context is already resolved by the
| 'tenant' middleware group applied to all tenant subdomain routes.
|
| Re-Authentication Pattern:
| Sensitive actions that modify user access or roles require password
| confirmation via the `password.confirm` middleware. This includes:
| - User role changes (PATCH /api/users/{user})
| - User deactivation/removal (DELETE /api/users/{user})
|
| Password confirmation is valid for 3 hours (configured in config/auth.php).
| See docs/re-authentication.md for detailed implementation guidance.
|
*/

Route::prefix('api')->middleware(['auth', 'subscribed'])->group(function () {
    // Always available (starter modules) — no module middleware
    require __DIR__.'/tenant/api-core.php';
    require __DIR__.'/tenant/api-employees.php';
    require __DIR__.'/tenant/api-organization.php';
    require __DIR__.'/tenant/api-hr-modules.php';

    // Module-gated API routes
    Route::middleware(['module:performance_management'])->group(function () {
        require __DIR__.'/tenant/api-performance.php';
    });

    Route::middleware(['module:recruitment,onboarding_preboarding'])->group(function () {
        require __DIR__.'/tenant/api-recruitment.php';
    });

    Route::middleware(['module:training_development'])->group(function () {
        require __DIR__.'/tenant/api-training.php';
    });

    Route::middleware(['module:visitor_management'])->group(function () {
        require __DIR__.'/tenant/api-visitors.php';
    });
});
