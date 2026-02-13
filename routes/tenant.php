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
    return Inertia::render('TenantDashboard', [
        'justCreated' => $request->boolean('created'),
    ]);
})->name('tenant.home');

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $tenant = app('tenant');
        if ($tenant) {
            $role = $user->getRoleInTenant($tenant);
            if ($role === \App\Enums\TenantUserRole::Employee) {
                return redirect()->route('my.dashboard');
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

Route::middleware(['auth'])->group(function () {
    require __DIR__.'/tenant/web-core.php';
    require __DIR__.'/tenant/web-employees.php';
    require __DIR__.'/tenant/web-organization.php';
    require __DIR__.'/tenant/web-modules.php';
    require __DIR__.'/tenant/web-recruitment.php';
    require __DIR__.'/tenant/web-self-service.php';
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

Route::prefix('api')->middleware(['auth'])->group(function () {
    require __DIR__.'/tenant/api-core.php';
    require __DIR__.'/tenant/api-employees.php';
    require __DIR__.'/tenant/api-organization.php';
    require __DIR__.'/tenant/api-hr-modules.php';
    require __DIR__.'/tenant/api-performance.php';
    require __DIR__.'/tenant/api-recruitment.php';
    require __DIR__.'/tenant/api-training.php';
});
