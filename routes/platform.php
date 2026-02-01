<?php

/**
 * Platform Routes
 *
 * Routes defined here are for the main domain (kasamahr.com / kasamahr.test).
 * These routes bypass tenant resolution and are used for marketing pages,
 * authentication, tenant selection, and tenant registration.
 */

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\TenantSelectorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Tenant Selection Routes
// These routes allow authenticated users to select which tenant to access
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/select-tenant', [TenantSelectorController::class, 'index'])
        ->name('tenant.select');

    Route::post('/select-tenant/{tenant}', [TenantSelectorController::class, 'select'])
        ->name('tenant.select.submit');
});

// Tenant Registration Routes
// These routes allow authenticated users to register new organizations
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/register-organization', [TenantRegistrationController::class, 'create'])
        ->name('tenant.register');

    Route::post('/register-organization', [TenantRegistrationController::class, 'store'])
        ->name('tenant.register.store');
});

// User Invitation Routes
// These routes allow invited users to accept their invitation and set a password
// No authentication required - users are accepting invitation before they have an account
Route::get('/invitations/{token}/accept', [InvitationController::class, 'show'])
    ->name('invitation.accept');

Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])
    ->name('invitation.accept.submit');

// Slug Availability API Endpoint
// Used for real-time validation in registration form
Route::get('/api/check-slug/{slug}', function (string $slug) {
    $exists = \App\Models\Tenant::where('slug', $slug)->exists();

    return response()->json([
        'slug' => $slug,
        'available' => ! $exists,
    ]);
})->name('api.check-slug');

require __DIR__.'/settings.php';
