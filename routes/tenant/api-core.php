<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\HelpArticleController;
use App\Http\Controllers\Api\HelpCategoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordConfirmationController;
use App\Http\Controllers\Api\TenantPayrollSettingsController;
use App\Http\Controllers\Api\TenantUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification Management API
|--------------------------------------------------------------------------
|
| These endpoints allow authenticated users to view and manage their
| notifications, including marking them as read and downloading
| associated files (such as bulk payslip PDFs).
|
*/

Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])
        ->name('api.notifications.index');
    Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.notifications.mark-as-read');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->name('api.notifications.mark-all-read');
    Route::get('/{notification}/download', [NotificationController::class, 'download'])
        ->name('api.notifications.download');
});

/*
|--------------------------------------------------------------------------
| Evaluation Response API (Employee Self-Service)
|--------------------------------------------------------------------------
|
| These endpoints allow employees to view and submit evaluation responses.
| Authorization is handled in the controller based on the reviewer assignment.
|
*/

Route::prefix('evaluation-reviewers/{reviewer}')->group(function () {
    Route::get('/response', [\App\Http\Controllers\Api\EvaluationResponseController::class, 'show'])
        ->name('api.evaluation-response.show');
    Route::post('/response', [\App\Http\Controllers\Api\EvaluationResponseController::class, 'store'])
        ->name('api.evaluation-response.store');
    Route::post('/submit', [\App\Http\Controllers\Api\EvaluationResponseController::class, 'submit'])
        ->name('api.evaluation-response.submit');
    Route::post('/decline', [\App\Http\Controllers\Api\EvaluationResponseController::class, 'decline'])
        ->name('api.evaluation-response.decline');
});

// Evaluation Summary Acknowledgement (Employee)
Route::post('/participants/{participant}/summary/acknowledge', [\App\Http\Controllers\Api\EvaluationSummaryController::class, 'acknowledge'])
    ->name('api.evaluation-summary.acknowledge');

/*
|--------------------------------------------------------------------------
| Password Confirmation API (Tenant-Scoped)
|--------------------------------------------------------------------------
|
| These endpoints mirror Fortify's password confirmation functionality
| but are scoped to the tenant subdomain to avoid cross-origin issues.
|
*/

Route::get('/password/confirmation-status', [PasswordConfirmationController::class, 'status'])
    ->name('api.tenant.password.confirmation');
Route::post('/password/confirm', [PasswordConfirmationController::class, 'store'])
    ->name('api.tenant.password.confirm');

// User Management API
// These endpoints allow Admin users to manage tenant members
Route::get('/users', [TenantUserController::class, 'index'])
    ->name('api.tenant.users.index');

Route::get('/employees/unlinked', [TenantUserController::class, 'unlinkedEmployees'])
    ->name('api.tenant.employees.unlinked');

Route::post('/users/invite', [TenantUserController::class, 'invite'])
    ->name('api.tenant.users.invite');

// Sensitive actions requiring password confirmation
// These routes use the tenant-safe password confirmation middleware
// that returns 423 JSON instead of redirecting to the main domain
Route::patch('/users/{user}', [TenantUserController::class, 'update'])
    ->middleware('tenant.password.confirm')
    ->name('api.tenant.users.update');

Route::delete('/users/{user}', [TenantUserController::class, 'destroy'])
    ->middleware('tenant.password.confirm')
    ->name('api.tenant.users.destroy');

Route::post('/users/{user}/send-account-setup', [TenantUserController::class, 'sendAccountSetupEmail'])
    ->name('api.tenant.users.send-account-setup');

/*
|--------------------------------------------------------------------------
| Tenant Settings API
|--------------------------------------------------------------------------
|
| These endpoints allow tenant admins to manage tenant-level settings
| including payroll configuration and holiday pay rates.
|
*/

Route::prefix('tenant')->group(function () {
    // Payroll Settings Management
    Route::get('/payroll-settings', [TenantPayrollSettingsController::class, 'show'])
        ->name('api.tenant.payroll-settings.show');
    Route::patch('/payroll-settings', [TenantPayrollSettingsController::class, 'update'])
        ->name('api.tenant.payroll-settings.update');
});

/*
|--------------------------------------------------------------------------
| Self-Service Clock API
|--------------------------------------------------------------------------
|
| These endpoints allow authenticated employees to clock in/out from their
| own devices when self-service clock-in is enabled for their work location.
|
*/

Route::prefix('clock')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\SelfServiceClockController::class, 'clock'])
        ->name('api.clock.store');
    Route::get('/status', [\App\Http\Controllers\Api\SelfServiceClockController::class, 'status'])
        ->name('api.clock.status');
});

// Self-service PIN reset
Route::post('/my/pin/reset', [\App\Http\Controllers\Api\EmployeePinController::class, 'resetOwn'])
    ->name('api.my.pin.reset');

/*
|--------------------------------------------------------------------------
| Audit Logs API
|--------------------------------------------------------------------------
|
| These endpoints allow Admin users to view audit logs for compliance.
| Supports filtering by model type, action, user, and date range.
|
*/

Route::prefix('settings/audit-logs')->group(function () {
    Route::get('/', [AuditLogController::class, 'index'])
        ->name('api.audit-logs.index');
    Route::get('/filters', [AuditLogController::class, 'filters'])
        ->name('api.audit-logs.filters');
});

/*
|--------------------------------------------------------------------------
| Help Content API
|--------------------------------------------------------------------------
|
| These endpoints allow Super Admins to manage help center content
| including categories and articles. Uses standard CRUD operations.
|
*/

Route::prefix('help')->name('api.help.')->group(function () {
    Route::apiResource('categories', HelpCategoryController::class);
    Route::apiResource('articles', HelpArticleController::class);
});
