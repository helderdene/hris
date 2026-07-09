<?php

use App\Http\Controllers\ExternalApi\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/employees', [EmployeeController::class, 'index'])->name('external-api.employees.index');
Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('external-api.employees.show');
