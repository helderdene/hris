<?php

use App\Http\Controllers\Help\HelpCenterController;
use App\Http\Controllers\Settings\AuditLogPageController;
use App\Http\Controllers\Settings\HelpAdminPageController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// User Management
// This page allows Admin users to manage tenant members
Route::get('/users', [UserController::class, 'index'])
    ->name('tenant.users.index');

// User Settings (profile, password, appearance, two-factor)
Route::redirect('/settings', '/settings/profile');

Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('tenant.profile.edit');
Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('tenant.profile.update');
Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('tenant.profile.destroy');

Route::get('/settings/password', [PasswordController::class, 'edit'])->name('tenant.user-password.edit');
Route::put('/settings/password', [PasswordController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('tenant.user-password.update');

Route::get('/settings/appearance', function () {
    return Inertia::render('settings/Appearance');
})->name('tenant.appearance.edit');

Route::get('/settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
    ->name('tenant.two-factor.show');

// Audit Logs
// This page allows Admin users to view all model changes for compliance
Route::get('/settings/audit-logs', AuditLogPageController::class)
    ->name('settings.audit-logs');

// Help Admin
// This page allows Super Admins to manage help center content
Route::get('/settings/help-admin', HelpAdminPageController::class)
    ->name('settings.help-admin');

/*
|--------------------------------------------------------------------------
| Help Center Routes
|--------------------------------------------------------------------------
|
| These routes render the help center pages where all authenticated users
| can access documentation, search for help articles, and browse categories.
|
*/
Route::prefix('help')->name('help.')->group(function () {
    Route::get('/', [HelpCenterController::class, 'index'])->name('index');
    Route::get('/search', [HelpCenterController::class, 'search'])->name('search');
    Route::get('/{categorySlug}', [HelpCenterController::class, 'showCategory'])->name('category');
    Route::get('/{categorySlug}/{articleSlug}', [HelpCenterController::class, 'showArticle'])->name('article');
});
