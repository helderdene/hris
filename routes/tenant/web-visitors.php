<?php

use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Visitor Management Web Routes
|--------------------------------------------------------------------------
|
| These routes render Inertia pages for the visitor management module.
|
*/

Route::prefix('visitors')->group(function () {
    Route::get('/', [VisitorController::class, 'index'])
        ->name('visitors.index');
    Route::get('/log', [VisitorController::class, 'log'])
        ->name('visitors.log');
});
