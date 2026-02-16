<?php

use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\DtrController;
use App\Http\Controllers\LoanApprovalPageController;
use App\Http\Controllers\Reports\BirReportPageController;
use App\Http\Controllers\Reports\PagibigReportPageController;
use App\Http\Controllers\Reports\PhilhealthReportPageController;
use App\Http\Controllers\Reports\SssReportPageController;
use App\Http\Controllers\TrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Performance Management Web Routes
|--------------------------------------------------------------------------
|
| These routes render the performance management pages including KPIs.
|
*/
Route::prefix('performance')->group(function () {
    Route::get('/kpis', [\App\Http\Controllers\Performance\KpiPageController::class, 'index'])
        ->name('performance.kpis.index');

    // Competency Evaluations
    Route::get('/competency-evaluations', [\App\Http\Controllers\Performance\CompetencyEvaluationPageController::class, 'index'])
        ->name('performance.competency-evaluations.index');
    Route::get('/competency-evaluations/participants/{participant}', [\App\Http\Controllers\Performance\CompetencyEvaluationPageController::class, 'show'])
        ->name('performance.competency-evaluations.show');

    // Goals Management (Admin/HR View)
    Route::get('/goals', [\App\Http\Controllers\Performance\GoalPageController::class, 'index'])
        ->name('performance.goals.index');
    Route::get('/goals/{goal}', [\App\Http\Controllers\Performance\GoalPageController::class, 'show'])
        ->name('performance.goals.show');

    // 360-Degree Evaluations (Admin/HR View)
    Route::get('/evaluations', [\App\Http\Controllers\Performance\EvaluationController::class, 'index'])
        ->name('performance.evaluations.index');
    Route::get('/evaluations/{participant}', [\App\Http\Controllers\Performance\EvaluationController::class, 'show'])
        ->name('performance.evaluations.show');

    // Development Plans (Admin/HR View)
    Route::get('/development-plans', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'index'])
        ->name('performance.development-plans.index');
    Route::get('/development-plans/{developmentPlan}', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'show'])
        ->name('performance.development-plans.show');
});

/*
|--------------------------------------------------------------------------
| Training Management Web Routes
|--------------------------------------------------------------------------
|
| These routes render the training management pages for course catalog.
|
*/
Route::prefix('training')->group(function () {
    Route::get('/courses', [TrainingController::class, 'coursesIndex'])
        ->name('training.courses.index');
    Route::get('/courses/{course}', [TrainingController::class, 'coursesShow'])
        ->name('training.courses.show');
    Route::get('/categories', [TrainingController::class, 'categoriesIndex'])
        ->name('training.categories.index');

    // Training Sessions
    Route::get('/sessions', [\App\Http\Controllers\Training\SessionPageController::class, 'index'])
        ->name('training.sessions.index');
    Route::get('/sessions/{session}', [\App\Http\Controllers\Training\SessionPageController::class, 'show'])
        ->name('training.sessions.show');

    // Training Calendar
    Route::get('/calendar', [\App\Http\Controllers\Training\CalendarPageController::class, 'index'])
        ->name('training.calendar.index');

    // Training Enrollment Approvals
    Route::get('/approvals', [\App\Http\Controllers\TrainingEnrollmentApprovalController::class, 'index'])
        ->name('training.approvals.index');

    // Training History
    Route::get('/history', [\App\Http\Controllers\Training\TrainingHistoryController::class, 'index'])
        ->name('training.history.index');
    Route::get('/history/export', [\App\Http\Controllers\Training\TrainingHistoryController::class, 'export'])
        ->name('training.history.export');
    Route::get('/history/employee/{employee}', [\App\Http\Controllers\Training\TrainingHistoryController::class, 'employeeHistory'])
        ->name('training.history.employee');
});

/*
|--------------------------------------------------------------------------
| Compliance Training Web Routes
|--------------------------------------------------------------------------
*/
Route::prefix('compliance')->group(function () {
    Route::get('/', [\App\Http\Controllers\ComplianceController::class, 'dashboard'])
        ->name('compliance.dashboard');
    Route::get('/courses', [\App\Http\Controllers\ComplianceController::class, 'coursesIndex'])
        ->name('compliance.courses.index');
    Route::get('/courses/create', [\App\Http\Controllers\ComplianceController::class, 'coursesCreate'])
        ->name('compliance.courses.create');
    Route::get('/courses/{complianceCourse}', [\App\Http\Controllers\ComplianceController::class, 'coursesShow'])
        ->name('compliance.courses.show');
    Route::get('/rules', [\App\Http\Controllers\ComplianceController::class, 'rulesIndex'])
        ->name('compliance.rules.index');
    Route::get('/assignments', [\App\Http\Controllers\ComplianceController::class, 'assignmentsIndex'])
        ->name('compliance.assignments.index');
    Route::get('/reports', [\App\Http\Controllers\ComplianceController::class, 'reportsIndex'])
        ->name('compliance.reports.index');
});

/*
|--------------------------------------------------------------------------
| Attendance Log Web Route
|--------------------------------------------------------------------------
|
| This route renders the attendance logs page with real-time updates.
|
*/
Route::get('/attendance', [AttendanceLogController::class, 'index'])
    ->name('attendance.index');

/*
|--------------------------------------------------------------------------
| Daily Time Record (DTR) Web Routes
|--------------------------------------------------------------------------
|
| These routes render the DTR pages for viewing and managing daily time
| records, including individual employee DTR views and period summaries.
|
*/
Route::prefix('time-attendance')->group(function () {
    Route::get('/dtr', [DtrController::class, 'index'])
        ->name('dtr.index');
    Route::get('/dtr/{employee}', [DtrController::class, 'show'])
        ->name('dtr.show');
    Route::get('/dtr/{employee}/export', [DtrController::class, 'export'])
        ->name('dtr.export');
});

/*
|--------------------------------------------------------------------------
| Payroll Web Routes
|--------------------------------------------------------------------------
|
| These routes render the payroll pages for viewing and managing payroll
| entries, payslips, and computation results.
|
*/
Route::prefix('payroll')->group(function () {
    Route::get('/periods/{period}/entries', [\App\Http\Controllers\Payroll\PayrollEntryPageController::class, 'index'])
        ->name('payroll.entries.index');
    Route::get('/entries/{entry}', [\App\Http\Controllers\Payroll\PayrollEntryPageController::class, 'show'])
        ->name('payroll.entries.show');

    // Loan Management
    Route::get('/loans', [\App\Http\Controllers\Payroll\LoanPageController::class, 'index'])
        ->name('payroll.loans.index');

    // Adjustment Management
    Route::get('/adjustments', [\App\Http\Controllers\Payroll\AdjustmentPageController::class, 'index'])
        ->name('payroll.adjustments.index');
});

/*
|--------------------------------------------------------------------------
| Reports Web Routes
|--------------------------------------------------------------------------
|
| These routes render the report generation pages for compliance and
| regulatory submissions.
|
*/
Route::prefix('reports')->group(function () {
    // SSS Compliance Reports
    Route::get('/sss', [SssReportPageController::class, 'index'])
        ->name('reports.sss.index');

    // PhilHealth Compliance Reports
    Route::get('/philhealth', [PhilhealthReportPageController::class, 'index'])
        ->name('reports.philhealth.index');

    // Pag-IBIG Compliance Reports
    Route::get('/pagibig', [PagibigReportPageController::class, 'index'])
        ->name('reports.pagibig.index');

    // BIR Compliance Reports
    Route::get('/bir', [BirReportPageController::class, 'index'])
        ->name('reports.bir.index');
});

/*
|--------------------------------------------------------------------------
| Leave Application Web Routes
|--------------------------------------------------------------------------
|
| These routes render the leave application pages including employee
| leave requests, pending approvals, and approval history.
|
*/
Route::prefix('leave')->group(function () {
    Route::get('/applications', [\App\Http\Controllers\Leave\LeaveApplicationPageController::class, 'index'])
        ->name('leave.applications.index');
    Route::get('/applications/{leave_application}', [\App\Http\Controllers\Leave\LeaveApplicationPageController::class, 'show'])
        ->name('leave.applications.show');
    Route::get('/approvals', [\App\Http\Controllers\Leave\LeaveApprovalPageController::class, 'index'])
        ->name('leave.approvals.index');
    Route::get('/calendar', [\App\Http\Controllers\Leave\LeaveCalendarPageController::class, 'index'])
        ->name('leave.calendar.index');
});

/*
|--------------------------------------------------------------------------
| Overtime Request Web Routes
|--------------------------------------------------------------------------
|
| These routes render the overtime request pages including employee
| overtime requests, pending approvals, and approval history.
|
*/
Route::prefix('overtime')->group(function () {
    Route::get('/requests', [\App\Http\Controllers\Overtime\OvertimeRequestPageController::class, 'index'])
        ->name('overtime.requests.index');
    Route::get('/requests/{overtime_request}', [\App\Http\Controllers\Overtime\OvertimeRequestPageController::class, 'show'])
        ->name('overtime.requests.show');
    Route::get('/approvals', [\App\Http\Controllers\Overtime\OvertimeApprovalPageController::class, 'index'])
        ->name('overtime.approvals.index');
});

/*
|--------------------------------------------------------------------------
| Loan Approval Management Web Routes
|--------------------------------------------------------------------------
|
| This route renders the loan approval page for HR staff to review
| and process employee loan applications.
|
*/
Route::get('/loan-approvals', [LoanApprovalPageController::class, 'index'])
    ->name('loan-approvals.index');
