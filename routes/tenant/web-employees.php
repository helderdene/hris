<?php

use App\Http\Controllers\AnnouncementPageController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDashboardController;
use App\Http\Controllers\HRAnalyticsDashboardController;
use App\Http\Controllers\PerformanceAnalyticsDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Employee Management Web Routes
|--------------------------------------------------------------------------
|
| These routes render the employee management pages including the employee
| dashboard, employee list, individual employee profiles, and create/edit forms.
|
*/
Route::get('/employees/dashboard', [EmployeeDashboardController::class, 'dashboard'])
    ->name('employees.dashboard');

/*
|--------------------------------------------------------------------------
| HR Analytics Dashboard
|--------------------------------------------------------------------------
|
| Comprehensive analytics dashboard for HR metrics including headcount,
| attendance, leave, compensation, recruitment, and performance data.
|
*/
Route::get('/hr/analytics', [HRAnalyticsDashboardController::class, 'index'])
    ->name('hr.analytics');

/*
|--------------------------------------------------------------------------
| Performance Analytics Dashboard
|--------------------------------------------------------------------------
|
| Comprehensive analytics dashboard for performance metrics including
| evaluations, ratings, development plans, goals, and KPIs.
|
*/
Route::get('/performance/analytics', PerformanceAnalyticsDashboardController::class)
    ->name('performance.analytics');

Route::get('/employees', [EmployeeController::class, 'index'])
    ->name('employees.index');
Route::get('/employees/create', [EmployeeController::class, 'create'])
    ->name('employees.create');
Route::post('/employees', [EmployeeController::class, 'store'])
    ->name('employees.store');
Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
    ->name('employees.show');
Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
    ->name('employees.edit');
Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
    ->name('employees.update');

/*
|--------------------------------------------------------------------------
| Company Documents Web Route
|--------------------------------------------------------------------------
|
| This route renders the company-wide documents page accessible to all
| tenant users. Document upload/delete is restricted to HR staff.
|
*/
Route::get('/company-documents', [CompanyDocumentController::class, 'index'])
    ->name('company-documents.index');

/*
|--------------------------------------------------------------------------
| Announcements Web Route
|--------------------------------------------------------------------------
|
| This route renders the announcements management page for admins and
| HR managers to create, edit, and delete company announcements.
|
*/
Route::get('/announcements', AnnouncementPageController::class)
    ->name('announcements.index');
