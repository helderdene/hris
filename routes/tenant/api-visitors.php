<?php

use App\Http\Controllers\Api\MyVisitorVisitController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Api\VisitorVisitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Visitor Management API
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users (Admin, HR Manager) to manage
| visitors, visitor visits, and the approval/check-in workflows.
|
*/

Route::prefix('visitors')->group(function () {
    Route::get('/', [VisitorController::class, 'index'])
        ->name('api.visitors.index');
    Route::post('/', [VisitorController::class, 'store'])
        ->name('api.visitors.store');
    Route::get('/{visitor}', [VisitorController::class, 'show'])
        ->name('api.visitors.show');
    Route::put('/{visitor}', [VisitorController::class, 'update'])
        ->name('api.visitors.update');
    Route::delete('/{visitor}', [VisitorController::class, 'destroy'])
        ->name('api.visitors.destroy');
});

Route::prefix('visitor-visits')->group(function () {
    // Export must be before {visit} to avoid route conflicts
    Route::get('/export', [VisitorVisitController::class, 'export'])
        ->name('api.visitor-visits.export');

    Route::get('/', [VisitorVisitController::class, 'index'])
        ->name('api.visitor-visits.index');
    Route::post('/', [VisitorVisitController::class, 'store'])
        ->name('api.visitor-visits.store');
    Route::get('/{visit}', [VisitorVisitController::class, 'show'])
        ->name('api.visitor-visits.show');
    Route::put('/{visit}', [VisitorVisitController::class, 'update'])
        ->name('api.visitor-visits.update');
    Route::delete('/{visit}', [VisitorVisitController::class, 'destroy'])
        ->name('api.visitor-visits.destroy');

    // Workflow actions
    Route::post('/{visit}/approve', [VisitorVisitController::class, 'approve'])
        ->name('api.visitor-visits.approve');
    Route::post('/{visit}/reject', [VisitorVisitController::class, 'reject'])
        ->name('api.visitor-visits.reject');
    Route::post('/{visit}/check-in', [VisitorVisitController::class, 'checkIn'])
        ->name('api.visitor-visits.check-in');
    Route::post('/{visit}/check-out', [VisitorVisitController::class, 'checkOut'])
        ->name('api.visitor-visits.check-out');
    Route::post('/{visit}/resend-qr', [VisitorVisitController::class, 'resendQrCode'])
        ->name('api.visitor-visits.resend-qr');
});

// Self-service visitor visit actions (host employee)
Route::prefix('my/visitor-visits')->group(function () {
    Route::post('/{visit}/approve', [MyVisitorVisitController::class, 'approve'])
        ->name('api.my.visitor-visits.approve');
    Route::post('/{visit}/reject', [MyVisitorVisitController::class, 'reject'])
        ->name('api.my.visitor-visits.reject');
});
