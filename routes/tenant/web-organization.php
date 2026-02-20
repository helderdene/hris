<?php

use App\Http\Controllers\ContributionController;
use App\Http\Controllers\Organization\CertificationTypePageController;
use App\Http\Controllers\Organization\LeaveBalancePageController;
use App\Http\Controllers\Organization\PayrollPeriodPageController;
use App\Http\Controllers\Organization\PerformanceCyclePageController;
use App\Http\Controllers\Organization\WorkScheduleController as WorkSchedulePageController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organization Management Web Routes
|--------------------------------------------------------------------------
|
| These routes render the organization management pages.
|
*/
Route::prefix('organization')->group(function () {
    Route::get('/departments', [OrganizationController::class, 'departmentsIndex'])
        ->name('organization.departments.index');
    Route::get('/org-chart', [OrganizationController::class, 'orgChart'])
        ->name('organization.org-chart');
    Route::get('/positions', [OrganizationController::class, 'positionsIndex'])
        ->name('organization.positions.index');
    Route::get('/salary-grades', [OrganizationController::class, 'salaryGradesIndex'])
        ->name('organization.salary-grades.index');
    Route::get('/locations', [OrganizationController::class, 'locationsIndex'])
        ->name('organization.locations.index');
    Route::get('/devices', [OrganizationController::class, 'devicesIndex'])
        ->name('organization.devices.index');
    Route::get('/kiosks', [OrganizationController::class, 'kiosksIndex'])
        ->name('organization.kiosks.index');
    Route::get('/work-schedules', [WorkSchedulePageController::class, 'index'])
        ->name('organization.work-schedules.index');
    Route::get('/holidays', [OrganizationController::class, 'holidaysIndex'])
        ->name('organization.holidays.index');
    Route::get('/leave-types', [OrganizationController::class, 'leaveTypesIndex'])
        ->name('organization.leave-types.index');
    Route::get('/leave-balances', [LeaveBalancePageController::class, 'index'])
        ->name('organization.leave-balances.index');
    Route::get('/payroll-periods', [PayrollPeriodPageController::class, 'index'])
        ->name('organization.payroll-periods.index');
    Route::get('/performance-cycles', [PerformanceCyclePageController::class, 'index'])
        ->name('organization.performance-cycles.index');
    Route::get('/competencies', [\App\Http\Controllers\Organization\CompetencyPageController::class, 'index'])
        ->name('organization.competencies.index');
    Route::get('/competency-matrix', [\App\Http\Controllers\Organization\CompetencyMatrixPageController::class, 'index'])
        ->name('organization.competency-matrix.index');
    Route::get('/certification-types', CertificationTypePageController::class)
        ->name('organization.certification-types.index');
});

Route::prefix('organization')->group(function () {
    // Government Contribution Management
    Route::prefix('contributions')->group(function () {
        Route::get('/sss', [ContributionController::class, 'sssIndex'])
            ->name('organization.contributions.sss.index');
        Route::get('/philhealth', [ContributionController::class, 'philhealthIndex'])
            ->name('organization.contributions.philhealth.index');
        Route::get('/pagibig', [ContributionController::class, 'pagibigIndex'])
            ->name('organization.contributions.pagibig.index');
        Route::get('/tax', [ContributionController::class, 'taxIndex'])
            ->name('organization.contributions.tax.index');
        Route::get('/calculator', [ContributionController::class, 'calculator'])
            ->name('organization.contributions.calculator');
    });
});
