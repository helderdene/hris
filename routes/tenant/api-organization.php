<?php

use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\ContributionCalculatorController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeScheduleAssignmentController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PagibigContributionController;
use App\Http\Controllers\Api\PayrollComputationController;
use App\Http\Controllers\Api\PayrollCycleController;
use App\Http\Controllers\Api\PayrollEntryController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\PerformanceCycleController;
use App\Http\Controllers\Api\PerformanceCycleInstanceController;
use App\Http\Controllers\Api\PerformanceCycleParticipantController;
use App\Http\Controllers\Api\PhilhealthContributionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\SssContributionController;
use App\Http\Controllers\Api\WithholdingTaxController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organization Management API
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users (Admin, HR Manager) to manage
| the organizational structure including departments, positions, salary
| grades, work locations, work schedules, and holidays.
|
*/

Route::prefix('organization')->group(function () {
    // Department Management
    Route::get('/departments', [DepartmentController::class, 'index'])
        ->name('api.organization.departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])
        ->name('api.organization.departments.store');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])
        ->name('api.organization.departments.show');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])
        ->name('api.organization.departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
        ->name('api.organization.departments.destroy');

    // Salary Grade Management
    Route::get('/salary-grades', [SalaryGradeController::class, 'index'])
        ->name('api.organization.salary-grades.index');
    Route::post('/salary-grades', [SalaryGradeController::class, 'store'])
        ->name('api.organization.salary-grades.store');
    Route::get('/salary-grades/{salary_grade}', [SalaryGradeController::class, 'show'])
        ->name('api.organization.salary-grades.show');
    Route::put('/salary-grades/{salary_grade}', [SalaryGradeController::class, 'update'])
        ->name('api.organization.salary-grades.update');
    Route::delete('/salary-grades/{salary_grade}', [SalaryGradeController::class, 'destroy'])
        ->name('api.organization.salary-grades.destroy');

    // Position Management
    Route::get('/positions', [PositionController::class, 'index'])
        ->name('api.organization.positions.index');
    Route::post('/positions', [PositionController::class, 'store'])
        ->name('api.organization.positions.store');
    Route::get('/positions/{position}', [PositionController::class, 'show'])
        ->name('api.organization.positions.show');
    Route::put('/positions/{position}', [PositionController::class, 'update'])
        ->name('api.organization.positions.update');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])
        ->name('api.organization.positions.destroy');

    // Work Location Management
    Route::get('/locations', [WorkLocationController::class, 'index'])
        ->name('api.organization.locations.index');
    Route::post('/locations', [WorkLocationController::class, 'store'])
        ->name('api.organization.locations.store');
    Route::get('/locations/{location}', [WorkLocationController::class, 'show'])
        ->name('api.organization.locations.show');
    Route::put('/locations/{location}', [WorkLocationController::class, 'update'])
        ->name('api.organization.locations.update');
    Route::delete('/locations/{location}', [WorkLocationController::class, 'destroy'])
        ->name('api.organization.locations.destroy');

    /*
    |--------------------------------------------------------------------------
    | Biometric Device Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage MQTT-enabled facial
    | recognition devices with real-time connection status monitoring,
    | scoped to specific work locations within the organization.
    |
    */

    // Biometric Device Management
    Route::get('/devices', [BiometricDeviceController::class, 'index'])
        ->name('api.organization.devices.index');
    Route::post('/devices', [BiometricDeviceController::class, 'store'])
        ->name('api.organization.devices.store');
    Route::get('/devices/{deviceId}', [BiometricDeviceController::class, 'show'])
        ->name('api.organization.devices.show');
    Route::put('/devices/{deviceId}', [BiometricDeviceController::class, 'update'])
        ->name('api.organization.devices.update');
    Route::delete('/devices/{deviceId}', [BiometricDeviceController::class, 'destroy'])
        ->name('api.organization.devices.destroy');

    /*
    |--------------------------------------------------------------------------
    | Kiosk Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage web kiosk terminals
    | for PIN-based attendance recording at work locations.
    |
    */

    Route::get('/kiosks', [\App\Http\Controllers\Api\KioskController::class, 'index'])
        ->name('api.organization.kiosks.index');
    Route::post('/kiosks', [\App\Http\Controllers\Api\KioskController::class, 'store'])
        ->name('api.organization.kiosks.store');
    Route::get('/kiosks/{kiosk}', [\App\Http\Controllers\Api\KioskController::class, 'show'])
        ->name('api.organization.kiosks.show');
    Route::put('/kiosks/{kiosk}', [\App\Http\Controllers\Api\KioskController::class, 'update'])
        ->name('api.organization.kiosks.update');
    Route::delete('/kiosks/{kiosk}', [\App\Http\Controllers\Api\KioskController::class, 'destroy'])
        ->name('api.organization.kiosks.destroy');
    Route::post('/kiosks/{kiosk}/regenerate-token', [\App\Http\Controllers\Api\KioskController::class, 'regenerateToken'])
        ->name('api.organization.kiosks.regenerate-token');

    /*
    |--------------------------------------------------------------------------
    | Biometric Device Sync API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage employee-to-device
    | synchronization including triggering syncs and viewing sync status.
    |
    */

    // Device Sync Management
    Route::prefix('devices/{device}')->group(function () {
        Route::get('/sync-status', [BiometricSyncController::class, 'deviceSyncStatus'])
            ->name('api.organization.devices.sync-status');
        Route::post('/sync-all', [BiometricSyncController::class, 'syncAllToDevice'])
            ->name('api.organization.devices.sync-all');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Schedule Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage work schedules
    | (Fixed, Flexible, Shifting, Compressed) with overtime rules and
    | night differential settings, as well as employee schedule assignments.
    |
    */

    // Work Schedule Management
    Route::get('/work-schedules', [WorkScheduleController::class, 'index'])
        ->name('api.organization.work-schedules.index');
    Route::post('/work-schedules', [WorkScheduleController::class, 'store'])
        ->name('api.organization.work-schedules.store');
    Route::get('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])
        ->name('api.organization.work-schedules.show');
    Route::put('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'update'])
        ->name('api.organization.work-schedules.update');
    Route::delete('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
        ->name('api.organization.work-schedules.destroy');

    // Employee Schedule Assignment Management (nested under work-schedules)
    Route::get('/work-schedules/{workSchedule}/assignments', [EmployeeScheduleAssignmentController::class, 'index'])
        ->name('api.organization.work-schedules.assignments.index');
    Route::post('/work-schedules/{workSchedule}/assignments', [EmployeeScheduleAssignmentController::class, 'store'])
        ->name('api.organization.work-schedules.assignments.store');
    Route::put('/work-schedules/{workSchedule}/assignments/{assignment}', [EmployeeScheduleAssignmentController::class, 'update'])
        ->name('api.organization.work-schedules.assignments.update');
    Route::delete('/work-schedules/{workSchedule}/assignments/{assignment}', [EmployeeScheduleAssignmentController::class, 'destroy'])
        ->name('api.organization.work-schedules.assignments.destroy');

    /*
    |--------------------------------------------------------------------------
    | Holiday Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage Philippine holidays
    | for payroll, DTR, and leave management. All tenant users can view
    | holidays, but only HR Manager and HR Staff can create, update, or
    | delete holidays.
    |
    */

    // Holiday calendar endpoint (must be before resource routes)
    Route::get('/holidays/calendar', [HolidayController::class, 'calendar'])
        ->name('api.organization.holidays.calendar');

    // Holiday copy-to-year endpoint (must be before resource routes)
    Route::post('/holidays/copy-to-year', [HolidayController::class, 'copyToYear'])
        ->name('api.organization.holidays.copy-to-year');

    // Holiday Management
    Route::get('/holidays', [HolidayController::class, 'index'])
        ->name('api.organization.holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])
        ->name('api.organization.holidays.store');
    Route::get('/holidays/{holiday}', [HolidayController::class, 'show'])
        ->name('api.organization.holidays.show');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])
        ->name('api.organization.holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])
        ->name('api.organization.holidays.destroy');

    /*
    |--------------------------------------------------------------------------
    | Leave Type Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage leave type configurations
    | including Philippine statutory leaves and custom company leave types.
    | All tenant users can view leave types, but only HR Manager and Admin
    | can create, update, or delete leave types.
    |
    */

    // Leave Type seed statutory endpoint (must be before resource routes)
    Route::post('/leave-types/seed-statutory', [LeaveTypeController::class, 'seedStatutory'])
        ->name('api.organization.leave-types.seed-statutory');

    // Leave Type Management
    Route::get('/leave-types', [LeaveTypeController::class, 'index'])
        ->name('api.organization.leave-types.index');
    Route::post('/leave-types', [LeaveTypeController::class, 'store'])
        ->name('api.organization.leave-types.store');
    Route::get('/leave-types/{leave_type}', [LeaveTypeController::class, 'show'])
        ->name('api.organization.leave-types.show');
    Route::put('/leave-types/{leave_type}', [LeaveTypeController::class, 'update'])
        ->name('api.organization.leave-types.update');
    Route::delete('/leave-types/{leave_type}', [LeaveTypeController::class, 'destroy'])
        ->name('api.organization.leave-types.destroy');

    /*
    |--------------------------------------------------------------------------
    | Leave Balance Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage employee leave balances
    | including viewing, adjusting, initializing, and processing year-end
    | carry-over and forfeiture.
    |
    */

    // Leave Balance helper endpoints (must be before resource routes)
    Route::get('/leave-balances/years', [LeaveBalanceController::class, 'availableYears'])
        ->name('api.organization.leave-balances.years');
    Route::get('/leave-balances/leave-types', [LeaveBalanceController::class, 'leaveTypes'])
        ->name('api.organization.leave-balances.leave-types');
    Route::get('/leave-balances/summary', [LeaveBalanceController::class, 'summary'])
        ->name('api.organization.leave-balances.summary');

    // Leave Balance bulk operations
    Route::post('/leave-balances/initialize', [LeaveBalanceController::class, 'initialize'])
        ->name('api.organization.leave-balances.initialize');
    Route::post('/leave-balances/process-year-end', [LeaveBalanceController::class, 'processYearEnd'])
        ->name('api.organization.leave-balances.process-year-end');

    // Leave Balance Management
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])
        ->name('api.organization.leave-balances.index');
    Route::get('/leave-balances/{balance}', [LeaveBalanceController::class, 'show'])
        ->name('api.organization.leave-balances.show');
    Route::post('/leave-balances/{balance}/adjust', [LeaveBalanceController::class, 'adjust'])
        ->name('api.organization.leave-balances.adjust');

    /*
    |--------------------------------------------------------------------------
    | Payroll Cycle & Period Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage payroll cycles
    | (semi-monthly, monthly, supplemental, etc.) and payroll periods
    | with status tracking and bulk period generation.
    |
    */

    // Payroll Cycle Management
    Route::get('/payroll-cycles', [PayrollCycleController::class, 'index'])
        ->name('api.organization.payroll-cycles.index');
    Route::post('/payroll-cycles', [PayrollCycleController::class, 'store'])
        ->name('api.organization.payroll-cycles.store');
    Route::get('/payroll-cycles/{payroll_cycle}', [PayrollCycleController::class, 'show'])
        ->name('api.organization.payroll-cycles.show');
    Route::put('/payroll-cycles/{payroll_cycle}', [PayrollCycleController::class, 'update'])
        ->name('api.organization.payroll-cycles.update');
    Route::delete('/payroll-cycles/{payroll_cycle}', [PayrollCycleController::class, 'destroy'])
        ->name('api.organization.payroll-cycles.destroy');

    // Payroll Period Management
    Route::get('/payroll-periods', [PayrollPeriodController::class, 'index'])
        ->name('api.organization.payroll-periods.index');
    Route::post('/payroll-periods', [PayrollPeriodController::class, 'store'])
        ->name('api.organization.payroll-periods.store');
    Route::get('/payroll-periods/summary', [PayrollPeriodController::class, 'summary'])
        ->name('api.organization.payroll-periods.summary');
    Route::post('/payroll-periods/generate', [PayrollPeriodController::class, 'generate'])
        ->name('api.organization.payroll-periods.generate');
    Route::get('/payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'show'])
        ->name('api.organization.payroll-periods.show');
    Route::put('/payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'update'])
        ->name('api.organization.payroll-periods.update');
    Route::patch('/payroll-periods/{payroll_period}/status', [PayrollPeriodController::class, 'updateStatus'])
        ->name('api.organization.payroll-periods.update-status');
    Route::delete('/payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'destroy'])
        ->name('api.organization.payroll-periods.destroy');

    /*
    |--------------------------------------------------------------------------
    | Performance Cycle Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users (Admin, HR Manager) to manage
    | performance cycles, instances, and participant assignments.
    |
    */

    // Performance Cycle Configuration
    Route::get('/performance-cycles', [PerformanceCycleController::class, 'index'])
        ->name('api.organization.performance-cycles.index');
    Route::post('/performance-cycles', [PerformanceCycleController::class, 'store'])
        ->name('api.organization.performance-cycles.store');
    Route::get('/performance-cycles/{performanceCycle}', [PerformanceCycleController::class, 'show'])
        ->name('api.organization.performance-cycles.show');
    Route::put('/performance-cycles/{performanceCycle}', [PerformanceCycleController::class, 'update'])
        ->name('api.organization.performance-cycles.update');
    Route::delete('/performance-cycles/{performanceCycle}', [PerformanceCycleController::class, 'destroy'])
        ->name('api.organization.performance-cycles.destroy');

    // Performance Cycle Instance Management
    Route::get('/performance-cycle-instances', [PerformanceCycleInstanceController::class, 'index'])
        ->name('api.organization.performance-cycle-instances.index');
    Route::post('/performance-cycle-instances', [PerformanceCycleInstanceController::class, 'store'])
        ->name('api.organization.performance-cycle-instances.store');
    Route::post('/performance-cycle-instances/generate', [PerformanceCycleInstanceController::class, 'generate'])
        ->name('api.organization.performance-cycle-instances.generate');
    Route::get('/performance-cycle-instances/{performanceCycleInstance}', [PerformanceCycleInstanceController::class, 'show'])
        ->name('api.organization.performance-cycle-instances.show');
    Route::put('/performance-cycle-instances/{performanceCycleInstance}', [PerformanceCycleInstanceController::class, 'update'])
        ->name('api.organization.performance-cycle-instances.update');
    Route::patch('/performance-cycle-instances/{performanceCycleInstance}/status', [PerformanceCycleInstanceController::class, 'updateStatus'])
        ->name('api.organization.performance-cycle-instances.update-status');
    Route::delete('/performance-cycle-instances/{performanceCycleInstance}', [PerformanceCycleInstanceController::class, 'destroy'])
        ->name('api.organization.performance-cycle-instances.destroy');

    // Performance Cycle Participant Management
    Route::get('/performance-cycle-instances/{performanceCycleInstance}/participants', [PerformanceCycleParticipantController::class, 'index'])
        ->name('api.organization.performance-cycle-participants.index');
    Route::post('/performance-cycle-instances/{performanceCycleInstance}/participants/assign', [PerformanceCycleParticipantController::class, 'assign'])
        ->name('api.organization.performance-cycle-participants.assign');
    Route::put('/performance-cycle-instances/{performanceCycleInstance}/participants/{participant}', [PerformanceCycleParticipantController::class, 'update'])
        ->name('api.organization.performance-cycle-participants.update');
    Route::delete('/performance-cycle-instances/{performanceCycleInstance}/participants/{participant}', [PerformanceCycleParticipantController::class, 'destroy'])
        ->name('api.organization.performance-cycle-participants.destroy');

    // 360-Degree Evaluation Reviewer Management
    Route::get('/participants/{participant}/reviewers', [\App\Http\Controllers\Api\EvaluationReviewerController::class, 'index'])
        ->name('api.performance.evaluation-reviewers.index');
    Route::post('/participants/{participant}/reviewers', [\App\Http\Controllers\Api\EvaluationReviewerController::class, 'store'])
        ->name('api.performance.evaluation-reviewers.store');
    Route::get('/participants/{participant}/reviewers/{reviewer}', [\App\Http\Controllers\Api\EvaluationReviewerController::class, 'show'])
        ->name('api.performance.evaluation-reviewers.show');
    Route::delete('/participants/{participant}/reviewers/{reviewer}', [\App\Http\Controllers\Api\EvaluationReviewerController::class, 'destroy'])
        ->name('api.performance.evaluation-reviewers.destroy');

    // Evaluation Summary & Calibration
    Route::get('/participants/{participant}/summary', [\App\Http\Controllers\Api\EvaluationSummaryController::class, 'show'])
        ->name('api.performance.evaluation-summary.show');
    Route::post('/participants/{participant}/summary/calibrate', [\App\Http\Controllers\Api\EvaluationSummaryController::class, 'calibrate'])
        ->name('api.performance.evaluation-summary.calibrate');
    Route::post('/participants/{participant}/summary/recalculate', [\App\Http\Controllers\Api\EvaluationSummaryController::class, 'recalculate'])
        ->name('api.performance.evaluation-summary.recalculate');

    /*
    |--------------------------------------------------------------------------
    | Payroll Computation & Entry Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to compute payroll for periods,
    | view payroll entries, update entry status, and generate payslips.
    |
    */

    // Payroll Computation
    Route::post('/payroll-periods/{payroll_period}/compute', [PayrollComputationController::class, 'compute'])
        ->name('api.organization.payroll.compute');
    Route::get('/payroll-periods/{payroll_period}/preview/{employee}', [PayrollComputationController::class, 'preview'])
        ->name('api.organization.payroll.preview');
    Route::post('/payroll-periods/{payroll_period}/recompute', [PayrollComputationController::class, 'recompute'])
        ->name('api.organization.payroll.recompute');

    // Payroll Entry Management
    Route::get('/payroll-periods/{payroll_period}/entries', [PayrollEntryController::class, 'index'])
        ->name('api.organization.payroll-entries.index');
    Route::get('/payroll-periods/{payroll_period}/entries/summary', [PayrollEntryController::class, 'summary'])
        ->name('api.organization.payroll-entries.summary');
    Route::post('/payroll-periods/{payroll_period}/entries/bulk-status', [PayrollEntryController::class, 'bulkUpdateStatus'])
        ->name('api.organization.payroll-entries.bulk-status');

    Route::get('/payroll-entries/{payroll_entry}', [PayrollEntryController::class, 'show'])
        ->name('api.organization.payroll-entries.show');
    Route::patch('/payroll-entries/{payroll_entry}/status', [PayrollEntryController::class, 'updateStatus'])
        ->name('api.organization.payroll-entries.update-status');
    Route::get('/payroll-entries/{payroll_entry}/payslip', [PayrollEntryController::class, 'payslip'])
        ->name('api.organization.payroll-entries.payslip');
    Route::get('/payroll-entries/{payroll_entry}/pdf', [PayrollEntryController::class, 'downloadPdf'])
        ->name('api.organization.payroll-entries.download-pdf');
    Route::post('/payroll-periods/{payroll_period}/payslips/bulk-pdf', [PayrollEntryController::class, 'downloadBulkPdf'])
        ->name('api.organization.payroll-entries.bulk-pdf');

    /*
    |--------------------------------------------------------------------------
    | Government Contribution Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage government contribution
    | tables (SSS, PhilHealth, Pag-IBIG) and calculate contributions.
    |
    */

    Route::prefix('contributions')->group(function () {
        // SSS Contribution Tables
        Route::get('/sss', [SssContributionController::class, 'index'])
            ->name('api.organization.contributions.sss.index');
        Route::post('/sss', [SssContributionController::class, 'store'])
            ->name('api.organization.contributions.sss.store');
        Route::get('/sss/{sssContributionTable}', [SssContributionController::class, 'show'])
            ->name('api.organization.contributions.sss.show');
        Route::put('/sss/{sssContributionTable}', [SssContributionController::class, 'update'])
            ->name('api.organization.contributions.sss.update');
        Route::delete('/sss/{sssContributionTable}', [SssContributionController::class, 'destroy'])
            ->name('api.organization.contributions.sss.destroy');

        // PhilHealth Contribution Tables
        Route::get('/philhealth', [PhilhealthContributionController::class, 'index'])
            ->name('api.organization.contributions.philhealth.index');
        Route::post('/philhealth', [PhilhealthContributionController::class, 'store'])
            ->name('api.organization.contributions.philhealth.store');
        Route::get('/philhealth/{philhealthContributionTable}', [PhilhealthContributionController::class, 'show'])
            ->name('api.organization.contributions.philhealth.show');
        Route::put('/philhealth/{philhealthContributionTable}', [PhilhealthContributionController::class, 'update'])
            ->name('api.organization.contributions.philhealth.update');
        Route::delete('/philhealth/{philhealthContributionTable}', [PhilhealthContributionController::class, 'destroy'])
            ->name('api.organization.contributions.philhealth.destroy');

        // Pag-IBIG Contribution Tables
        Route::get('/pagibig', [PagibigContributionController::class, 'index'])
            ->name('api.organization.contributions.pagibig.index');
        Route::post('/pagibig', [PagibigContributionController::class, 'store'])
            ->name('api.organization.contributions.pagibig.store');
        Route::get('/pagibig/{pagibigContributionTable}', [PagibigContributionController::class, 'show'])
            ->name('api.organization.contributions.pagibig.show');
        Route::put('/pagibig/{pagibigContributionTable}', [PagibigContributionController::class, 'update'])
            ->name('api.organization.contributions.pagibig.update');
        Route::delete('/pagibig/{pagibigContributionTable}', [PagibigContributionController::class, 'destroy'])
            ->name('api.organization.contributions.pagibig.destroy');

        // Withholding Tax Tables
        Route::get('/tax', [WithholdingTaxController::class, 'index'])
            ->name('api.organization.contributions.tax.index');
        Route::post('/tax', [WithholdingTaxController::class, 'store'])
            ->name('api.organization.contributions.tax.store');
        Route::get('/tax/{withholdingTaxTable}', [WithholdingTaxController::class, 'show'])
            ->name('api.organization.contributions.tax.show');
        Route::put('/tax/{withholdingTaxTable}', [WithholdingTaxController::class, 'update'])
            ->name('api.organization.contributions.tax.update');
        Route::delete('/tax/{withholdingTaxTable}', [WithholdingTaxController::class, 'destroy'])
            ->name('api.organization.contributions.tax.destroy');

        // Contribution Calculator
        Route::post('/calculate', [ContributionCalculatorController::class, 'calculate'])
            ->name('api.organization.contributions.calculate');
        Route::post('/calculate/sss', [ContributionCalculatorController::class, 'calculateSss'])
            ->name('api.organization.contributions.calculate.sss');
        Route::post('/calculate/philhealth', [ContributionCalculatorController::class, 'calculatePhilhealth'])
            ->name('api.organization.contributions.calculate.philhealth');
        Route::post('/calculate/pagibig', [ContributionCalculatorController::class, 'calculatePagibig'])
            ->name('api.organization.contributions.calculate.pagibig');
        Route::get('/status', [ContributionCalculatorController::class, 'status'])
            ->name('api.organization.contributions.status');
    });
});
