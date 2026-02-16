<?php

use App\Http\Controllers\Api\BirReportController;
use App\Http\Controllers\Api\DailyTimeRecordController;
use App\Http\Controllers\Api\DocumentRequestController;
use App\Http\Controllers\Api\EmployeeBir2316Controller;
use App\Http\Controllers\Api\EmployeeLoanController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\PagibigReportController;
use App\Http\Controllers\Api\PhilhealthReportController;
use App\Http\Controllers\Api\SssReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Time & Attendance API Routes
|--------------------------------------------------------------------------
|
| These endpoints provide access to Daily Time Records (DTR), including
| viewing, calculating, and managing employee attendance records.
|
*/

Route::prefix('time-attendance')->group(function () {
    // DTR List and Filtered Views
    Route::get('/dtr', [DailyTimeRecordController::class, 'index'])
        ->name('api.dtr.index');
    Route::get('/dtr/needs-review', [DailyTimeRecordController::class, 'needsReview'])
        ->name('api.dtr.needs-review');
    Route::get('/dtr/pending-overtime', [DailyTimeRecordController::class, 'pendingOvertime'])
        ->name('api.dtr.pending-overtime');

    // Single DTR Record Actions
    Route::get('/dtr/record/{dailyTimeRecord}', [DailyTimeRecordController::class, 'show'])
        ->name('api.dtr.show');
    Route::post('/dtr/record/{dailyTimeRecord}/approve-overtime', [DailyTimeRecordController::class, 'approveOvertime'])
        ->name('api.dtr.approve-overtime');
    Route::post('/dtr/record/{dailyTimeRecord}/resolve-review', [DailyTimeRecordController::class, 'resolveReview'])
        ->name('api.dtr.resolve-review');
    Route::post('/dtr/record/{dailyTimeRecord}/deny-overtime', [DailyTimeRecordController::class, 'denyOvertime'])
        ->name('api.dtr.deny-overtime');
    Route::post('/dtr/record/{dailyTimeRecord}/remarks', [DailyTimeRecordController::class, 'updateRemarks'])
        ->name('api.dtr.update-remarks');

    // Employee-specific DTR endpoints
    Route::get('/dtr/employee/{employee}', [DailyTimeRecordController::class, 'employeeDtr'])
        ->name('api.dtr.employee');
    Route::get('/dtr/employee/{employee}/summary', [DailyTimeRecordController::class, 'summary'])
        ->name('api.dtr.employee.summary');
    Route::post('/dtr/employee/{employee}/calculate', [DailyTimeRecordController::class, 'calculate'])
        ->name('api.dtr.employee.calculate');
    Route::post('/dtr/employee/{employee}/calculate-range', [DailyTimeRecordController::class, 'calculateRange'])
        ->name('api.dtr.employee.calculate-range');
});

/*
|--------------------------------------------------------------------------
| Loan Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to manage employee loans including
| SSS loans, Pag-IBIG loans, and company loans with automatic payroll
| deduction integration.
|
*/

Route::prefix('loans')->group(function () {
    Route::get('/', [EmployeeLoanController::class, 'index'])
        ->name('api.loans.index');
    Route::post('/', [EmployeeLoanController::class, 'store'])
        ->name('api.loans.store');
    Route::get('/{loan}', [EmployeeLoanController::class, 'show'])
        ->name('api.loans.show');
    Route::put('/{loan}', [EmployeeLoanController::class, 'update'])
        ->name('api.loans.update');
    Route::delete('/{loan}', [EmployeeLoanController::class, 'destroy'])
        ->name('api.loans.destroy');
    Route::patch('/{loan}/status', [EmployeeLoanController::class, 'updateStatus'])
        ->name('api.loans.update-status');
    Route::post('/{loan}/payment', [EmployeeLoanController::class, 'recordPayment'])
        ->name('api.loans.record-payment');
});

// Employee loans endpoint
Route::get('/employees/{employee}/loans', [EmployeeLoanController::class, 'employeeLoans'])
    ->name('api.employees.loans');

/*
|--------------------------------------------------------------------------
| Employee Adjustment Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to manage employee payroll
| adjustments including allowances, bonuses, deductions, and loan-type
| adjustments with balance tracking.
|
*/

Route::prefix('adjustments')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'index'])
        ->name('api.adjustments.index');
    Route::post('/', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'store'])
        ->name('api.adjustments.store');
    Route::get('/{adjustment}', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'show'])
        ->name('api.adjustments.show');
    Route::put('/{adjustment}', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'update'])
        ->name('api.adjustments.update');
    Route::delete('/{adjustment}', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'destroy'])
        ->name('api.adjustments.destroy');
    Route::patch('/{adjustment}/status', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'updateStatus'])
        ->name('api.adjustments.update-status');
});

// Employee adjustments endpoint
Route::get('/employees/{employee}/adjustments', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'employeeAdjustments'])
    ->name('api.employees.adjustments');

// Employee leave balances endpoint
Route::get('/employees/{employee}/leave-balances', [LeaveBalanceController::class, 'employeeBalances'])
    ->name('api.employees.leave-balances');

// Period adjustments endpoint
Route::get('/organization/payroll-periods/{payroll_period}/adjustments', [\App\Http\Controllers\Api\EmployeeAdjustmentController::class, 'periodAdjustments'])
    ->name('api.organization.payroll-periods.adjustments');

/*
|--------------------------------------------------------------------------
| SSS Compliance Reports API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to generate SSS compliance reports
| (R3, R5, SBR, ECL) in various formats for regulatory submission.
|
*/

Route::prefix('reports/sss')->group(function () {
    Route::get('/periods', [SssReportController::class, 'periods'])
        ->name('api.reports.sss.periods');
    Route::post('/preview', [SssReportController::class, 'preview'])
        ->name('api.reports.sss.preview');
    Route::post('/summary', [SssReportController::class, 'summary'])
        ->name('api.reports.sss.summary');
    Route::post('/generate', [SssReportController::class, 'generate'])
        ->name('api.reports.sss.generate');
});

/*
|--------------------------------------------------------------------------
| PhilHealth Compliance Reports API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to generate PhilHealth compliance
| reports (RF1, ER2, MDR) in various formats for regulatory submission.
|
*/

Route::prefix('reports/philhealth')->group(function () {
    Route::get('/periods', [PhilhealthReportController::class, 'periods'])
        ->name('api.reports.philhealth.periods');
    Route::post('/preview', [PhilhealthReportController::class, 'preview'])
        ->name('api.reports.philhealth.preview');
    Route::post('/summary', [PhilhealthReportController::class, 'summary'])
        ->name('api.reports.philhealth.summary');
    Route::post('/generate', [PhilhealthReportController::class, 'generate'])
        ->name('api.reports.philhealth.generate');
});

/*
|--------------------------------------------------------------------------
| Pag-IBIG Compliance Reports API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to generate Pag-IBIG compliance
| reports (MCRF, STL, HDL) in various formats for regulatory submission.
|
*/

Route::prefix('reports/pagibig')->group(function () {
    Route::get('/periods', [PagibigReportController::class, 'periods'])
        ->name('api.reports.pagibig.periods');
    Route::post('/preview', [PagibigReportController::class, 'preview'])
        ->name('api.reports.pagibig.preview');
    Route::post('/summary', [PagibigReportController::class, 'summary'])
        ->name('api.reports.pagibig.summary');
    Route::post('/generate', [PagibigReportController::class, 'generate'])
        ->name('api.reports.pagibig.generate');
});

/*
|--------------------------------------------------------------------------
| BIR Compliance Reports API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow authorized users to generate BIR compliance
| reports (1601-C) in various formats for regulatory submission.
|
*/

Route::prefix('reports/bir')->group(function () {
    Route::get('/periods', [BirReportController::class, 'periods'])
        ->name('api.reports.bir.periods');
    Route::post('/preview', [BirReportController::class, 'preview'])
        ->name('api.reports.bir.preview');
    Route::post('/summary', [BirReportController::class, 'summary'])
        ->name('api.reports.bir.summary');
    Route::post('/generate', [BirReportController::class, 'generate'])
        ->name('api.reports.bir.generate');

    // BIR 2316 specific routes
    Route::post('/2316/generate-all', [BirReportController::class, 'generateBulk2316'])
        ->name('api.reports.bir.2316.generate-all');
    Route::get('/2316/{employee}/download', [BirReportController::class, 'download2316'])
        ->name('api.reports.bir.2316.download');
});

/*
|--------------------------------------------------------------------------
| Employee Self-Service API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to access their own documents and
| certificates through self-service.
|
*/
Route::prefix('my/bir-2316')->group(function () {
    Route::get('/', [EmployeeBir2316Controller::class, 'index'])
        ->name('api.my.bir-2316.index');
    Route::get('/{year}/download', [EmployeeBir2316Controller::class, 'download'])
        ->name('api.my.bir-2316.download');
});

// Admin Document Request Management
Route::patch('/document-requests/{document_request}', [\App\Http\Controllers\Api\DocumentRequestAdminController::class, 'update'])
    ->name('api.document-requests.update');
/*
|--------------------------------------------------------------------------
| Document Request Self-Service API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to submit and track document requests
| such as COE, employment verification, and payslip copies.
|
*/
Route::prefix('document-requests')->group(function () {
    Route::get('/', [DocumentRequestController::class, 'index'])
        ->name('api.document-requests.index');
    Route::post('/', [DocumentRequestController::class, 'store'])
        ->name('api.document-requests.store');
    Route::get('/{document_request}', [DocumentRequestController::class, 'show'])
        ->name('api.document-requests.show');
});

/*
|--------------------------------------------------------------------------
| Pre-boarding API Routes
|--------------------------------------------------------------------------
*/
Route::post('/preboarding-items/{item}/submit', [\App\Http\Controllers\Api\PreboardingSubmissionController::class, 'store'])
    ->name('api.preboarding-items.submit');
Route::post('/preboarding-items/{item}/approve', [\App\Http\Controllers\Api\PreboardingReviewController::class, 'approve'])
    ->name('api.preboarding-items.approve');
Route::post('/preboarding-items/{item}/reject', [\App\Http\Controllers\Api\PreboardingReviewController::class, 'reject'])
    ->name('api.preboarding-items.reject');
Route::apiResource('preboarding-templates', \App\Http\Controllers\Api\PreboardingTemplateController::class)
    ->names('api.preboarding-templates');
Route::post('/preboarding-checklists/{checklist}/convert-to-employee', [\App\Http\Controllers\Api\PreboardingReviewController::class, 'convertToEmployee'])
    ->name('api.preboarding-checklists.convert-to-employee');

/*
|--------------------------------------------------------------------------
| Onboarding API Routes
|--------------------------------------------------------------------------
*/
Route::post('/onboarding-items/{item}/complete', [\App\Http\Controllers\Api\OnboardingController::class, 'completeItem'])
    ->name('api.onboarding-items.complete');
Route::post('/onboarding-items/{item}/skip', [\App\Http\Controllers\Api\OnboardingController::class, 'skipItem'])
    ->name('api.onboarding-items.skip');
Route::post('/onboarding-items/{item}/assign', [\App\Http\Controllers\Api\OnboardingController::class, 'assignItem'])
    ->name('api.onboarding-items.assign');
Route::post('/onboarding-items/{item}/start', [\App\Http\Controllers\Api\OnboardingController::class, 'startItem'])
    ->name('api.onboarding-items.start');
Route::apiResource('onboarding-templates', \App\Http\Controllers\Api\OnboardingTemplateController::class)
    ->names('api.onboarding-templates');
Route::post('/onboarding-templates/{onboardingTemplate}/toggle-active', [\App\Http\Controllers\Api\OnboardingTemplateController::class, 'toggleActive'])
    ->name('api.onboarding-templates.toggle-active');

/*
|--------------------------------------------------------------------------
| Leave Application Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to submit leave applications and
| managers to approve/reject leave requests through a multi-level
| approval workflow.
|
*/

Route::prefix('leave-applications')->group(function () {
    // Employee's own applications
    Route::get('/my', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'myApplications'])
        ->name('api.leave-applications.my');

    // CRUD operations
    Route::get('/', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'index'])
        ->name('api.leave-applications.index');
    Route::post('/', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'store'])
        ->name('api.leave-applications.store');
    Route::get('/{leave_application}', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'show'])
        ->name('api.leave-applications.show');
    Route::put('/{leave_application}', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'update'])
        ->name('api.leave-applications.update');
    Route::delete('/{leave_application}', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'destroy'])
        ->name('api.leave-applications.destroy');

    // Workflow actions
    Route::post('/{leave_application}/submit', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'submit'])
        ->name('api.leave-applications.submit');
    Route::post('/{leave_application}/cancel', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'cancel'])
        ->name('api.leave-applications.cancel');
});

// Employee-specific leave applications endpoint
Route::get('/employees/{employee}/leave-applications', [\App\Http\Controllers\Api\LeaveApplicationController::class, 'employeeApplications'])
    ->name('api.employees.leave-applications');

Route::prefix('leave-approvals')->group(function () {
    // Pending approvals for current user
    Route::get('/pending', [\App\Http\Controllers\Api\LeaveApprovalController::class, 'pending'])
        ->name('api.leave-approvals.pending');
    Route::get('/summary', [\App\Http\Controllers\Api\LeaveApprovalController::class, 'summary'])
        ->name('api.leave-approvals.summary');
    Route::get('/history', [\App\Http\Controllers\Api\LeaveApprovalController::class, 'history'])
        ->name('api.leave-approvals.history');

    // Approval actions
    Route::post('/{leave_application}/approve', [\App\Http\Controllers\Api\LeaveApprovalController::class, 'approve'])
        ->name('api.leave-approvals.approve');
    Route::post('/{leave_application}/reject', [\App\Http\Controllers\Api\LeaveApprovalController::class, 'reject'])
        ->name('api.leave-approvals.reject');
});

/*
|--------------------------------------------------------------------------
| Action Center Inline Approval API Routes
|--------------------------------------------------------------------------
|
| These endpoints provide inline approval/rejection functionality for
| the Action Center Dashboard, allowing quick actions without navigation.
|
*/

Route::prefix('action-center')->group(function () {
    // Leave approval actions
    Route::post('/leave-approvals/{leaveApplication}/approve', [\App\Http\Controllers\Api\InlineApprovalController::class, 'approveLeave'])
        ->name('api.action-center.leave-approvals.approve');
    Route::post('/leave-approvals/{leaveApplication}/reject', [\App\Http\Controllers\Api\InlineApprovalController::class, 'rejectLeave'])
        ->name('api.action-center.leave-approvals.reject');

    // Overtime request approval actions
    Route::post('/overtime-approvals/{overtimeRequest}/approve', [\App\Http\Controllers\Api\InlineApprovalController::class, 'approveOvertimeRequest'])
        ->name('api.action-center.overtime-approvals.approve');
    Route::post('/overtime-approvals/{overtimeRequest}/reject', [\App\Http\Controllers\Api\InlineApprovalController::class, 'rejectOvertimeRequest'])
        ->name('api.action-center.overtime-approvals.reject');

    // Job requisition approval actions
    Route::post('/requisitions/{jobRequisition}/approve', [\App\Http\Controllers\Api\InlineApprovalController::class, 'approveRequisition'])
        ->name('api.action-center.requisitions.approve');
    Route::post('/requisitions/{jobRequisition}/reject', [\App\Http\Controllers\Api\InlineApprovalController::class, 'rejectRequisition'])
        ->name('api.action-center.requisitions.reject');
});

/*
|--------------------------------------------------------------------------
| Overtime Request Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to submit overtime requests and
| managers to approve/reject overtime requests through a multi-level
| approval workflow.
|
*/

Route::prefix('overtime-requests')->group(function () {
    // Employee's own requests
    Route::get('/my', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'myRequests'])
        ->name('api.overtime-requests.my');

    // CRUD operations
    Route::get('/', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'index'])
        ->name('api.overtime-requests.index');
    Route::post('/', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'store'])
        ->name('api.overtime-requests.store');
    Route::get('/{overtime_request}', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'show'])
        ->name('api.overtime-requests.show');
    Route::put('/{overtime_request}', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'update'])
        ->name('api.overtime-requests.update');
    Route::delete('/{overtime_request}', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'destroy'])
        ->name('api.overtime-requests.destroy');

    // Workflow actions
    Route::post('/{overtime_request}/submit', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'submit'])
        ->name('api.overtime-requests.submit');
    Route::post('/{overtime_request}/cancel', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'cancel'])
        ->name('api.overtime-requests.cancel');
});

// Employee-specific overtime requests endpoint
Route::get('/employees/{employee}/overtime-requests', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'employeeRequests'])
    ->name('api.employees.overtime-requests');

Route::prefix('overtime-approvals')->group(function () {
    // Pending approvals for current user
    Route::get('/pending', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'pendingApprovals'])
        ->name('api.overtime-approvals.pending');

    // Approval actions
    Route::post('/{overtime_request}/approve', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'approve'])
        ->name('api.overtime-approvals.approve');
    Route::post('/{overtime_request}/reject', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'reject'])
        ->name('api.overtime-approvals.reject');
});

/*
|--------------------------------------------------------------------------
| Leave Calendar API Route
|--------------------------------------------------------------------------
|
| This endpoint returns leave applications for calendar display,
| filtered by month and optionally by department.
|
*/
Route::get('/leave-calendar', [\App\Http\Controllers\Api\LeaveCalendarController::class, 'index'])
    ->name('api.leave-calendar.index');

/*
|--------------------------------------------------------------------------
| Loan Application Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to submit loan applications and
| HR staff to review and approve/reject loan requests.
|
*/

Route::prefix('loan-applications')->group(function () {
    // Employee's own applications
    Route::get('/my', [\App\Http\Controllers\Api\LoanApplicationController::class, 'myApplications'])
        ->name('api.loan-applications.my');

    // CRUD operations
    Route::get('/', [\App\Http\Controllers\Api\LoanApplicationController::class, 'index'])
        ->name('api.loan-applications.index');
    Route::post('/', [\App\Http\Controllers\Api\LoanApplicationController::class, 'store'])
        ->name('api.loan-applications.store');
    Route::get('/{loan_application}', [\App\Http\Controllers\Api\LoanApplicationController::class, 'show'])
        ->name('api.loan-applications.show');
    Route::put('/{loan_application}', [\App\Http\Controllers\Api\LoanApplicationController::class, 'update'])
        ->name('api.loan-applications.update');
    Route::delete('/{loan_application}', [\App\Http\Controllers\Api\LoanApplicationController::class, 'destroy'])
        ->name('api.loan-applications.destroy');

    // Workflow actions
    Route::post('/{loan_application}/submit', [\App\Http\Controllers\Api\LoanApplicationController::class, 'submit'])
        ->name('api.loan-applications.submit');
    Route::post('/{loan_application}/cancel', [\App\Http\Controllers\Api\LoanApplicationController::class, 'cancel'])
        ->name('api.loan-applications.cancel');
});

Route::prefix('loan-approvals')->group(function () {
    Route::get('/pending', [\App\Http\Controllers\Api\LoanApprovalController::class, 'pending'])
        ->name('api.loan-approvals.pending');
    Route::post('/{loan_application}/approve', [\App\Http\Controllers\Api\LoanApprovalController::class, 'approve'])
        ->name('api.loan-approvals.approve');
    Route::post('/{loan_application}/reject', [\App\Http\Controllers\Api\LoanApprovalController::class, 'reject'])
        ->name('api.loan-approvals.reject');
});

/*
|--------------------------------------------------------------------------
| Job Requisition Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to submit job requisitions and
| managers to approve/reject requisition requests through a multi-level
| approval workflow.
|
*/

Route::prefix('job-requisitions')->group(function () {
    // Employee's own requisitions
    Route::get('/my', [\App\Http\Controllers\Api\JobRequisitionController::class, 'myRequisitions'])
        ->name('api.job-requisitions.my');

    // CRUD operations
    Route::get('/', [\App\Http\Controllers\Api\JobRequisitionController::class, 'index'])
        ->name('api.job-requisitions.index');
    Route::post('/', [\App\Http\Controllers\Api\JobRequisitionController::class, 'store'])
        ->name('api.job-requisitions.store');
    Route::get('/{job_requisition}', [\App\Http\Controllers\Api\JobRequisitionController::class, 'show'])
        ->name('api.job-requisitions.show');
    Route::put('/{job_requisition}', [\App\Http\Controllers\Api\JobRequisitionController::class, 'update'])
        ->name('api.job-requisitions.update');
    Route::delete('/{job_requisition}', [\App\Http\Controllers\Api\JobRequisitionController::class, 'destroy'])
        ->name('api.job-requisitions.destroy');

    // Workflow actions
    Route::post('/{job_requisition}/submit', [\App\Http\Controllers\Api\JobRequisitionController::class, 'submit'])
        ->name('api.job-requisitions.submit');
    Route::post('/{job_requisition}/cancel', [\App\Http\Controllers\Api\JobRequisitionController::class, 'cancel'])
        ->name('api.job-requisitions.cancel');
});

Route::prefix('job-requisition-approvals')->group(function () {
    // Pending approvals for current user
    Route::get('/pending', [\App\Http\Controllers\Api\JobRequisitionApprovalController::class, 'pending'])
        ->name('api.job-requisition-approvals.pending');
    Route::get('/summary', [\App\Http\Controllers\Api\JobRequisitionApprovalController::class, 'summary'])
        ->name('api.job-requisition-approvals.summary');
    Route::get('/history', [\App\Http\Controllers\Api\JobRequisitionApprovalController::class, 'history'])
        ->name('api.job-requisition-approvals.history');

    // Approval actions
    Route::post('/{job_requisition}/approve', [\App\Http\Controllers\Api\JobRequisitionApprovalController::class, 'approve'])
        ->name('api.job-requisition-approvals.approve');
    Route::post('/{job_requisition}/reject', [\App\Http\Controllers\Api\JobRequisitionApprovalController::class, 'reject'])
        ->name('api.job-requisition-approvals.reject');
});

/*
|--------------------------------------------------------------------------
| Certification Management API Routes
|--------------------------------------------------------------------------
|
| These endpoints allow employees to manage their professional certifications
| and licenses, and HR staff to review, approve, and track certifications
| across the organization.
|
*/

// Certification Types (Admin/HR)
Route::prefix('certification-types')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CertificationTypeController::class, 'index'])
        ->name('api.certification-types.index');
    Route::post('/', [\App\Http\Controllers\Api\CertificationTypeController::class, 'store'])
        ->name('api.certification-types.store');
    Route::get('/{certification_type}', [\App\Http\Controllers\Api\CertificationTypeController::class, 'show'])
        ->name('api.certification-types.show');
    Route::put('/{certification_type}', [\App\Http\Controllers\Api\CertificationTypeController::class, 'update'])
        ->name('api.certification-types.update');
    Route::delete('/{certification_type}', [\App\Http\Controllers\Api\CertificationTypeController::class, 'destroy'])
        ->name('api.certification-types.destroy');
});

// Certifications (HR view - all employees)
Route::prefix('certifications')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\CertificationController::class, 'index'])
        ->name('api.certifications.index');
    Route::get('/statistics', [\App\Http\Controllers\Api\CertificationController::class, 'statistics'])
        ->name('api.certifications.statistics');
    Route::get('/{certification}', [\App\Http\Controllers\Api\CertificationController::class, 'show'])
        ->name('api.certifications.show');
    Route::post('/{certification}/approve', [\App\Http\Controllers\Api\CertificationController::class, 'approve'])
        ->name('api.certifications.approve');
    Route::post('/{certification}/reject', [\App\Http\Controllers\Api\CertificationController::class, 'reject'])
        ->name('api.certifications.reject');
    Route::post('/{certification}/revoke', [\App\Http\Controllers\Api\CertificationController::class, 'revoke'])
        ->name('api.certifications.revoke');

    // Certification Files
    Route::post('/{certification}/files', [\App\Http\Controllers\Api\CertificationFileController::class, 'store'])
        ->name('api.certifications.files.store');
    Route::get('/{certification}/files/{file}/download', [\App\Http\Controllers\Api\CertificationFileController::class, 'download'])
        ->name('api.certifications.files.download');
    Route::get('/{certification}/files/{file}/preview', [\App\Http\Controllers\Api\CertificationFileController::class, 'preview'])
        ->name('api.certifications.files.preview');
    Route::delete('/{certification}/files/{file}', [\App\Http\Controllers\Api\CertificationFileController::class, 'destroy'])
        ->name('api.certifications.files.destroy');
});

// My Certifications (Employee self-service)
Route::prefix('my/certifications')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\MyCertificationController::class, 'index'])
        ->name('api.my.certifications.index');
    Route::get('/statistics', [\App\Http\Controllers\Api\MyCertificationController::class, 'statistics'])
        ->name('api.my.certifications.statistics');
    Route::post('/', [\App\Http\Controllers\Api\MyCertificationController::class, 'store'])
        ->name('api.my.certifications.store');
    Route::get('/{certification}', [\App\Http\Controllers\Api\MyCertificationController::class, 'show'])
        ->name('api.my.certifications.show');
    Route::put('/{certification}', [\App\Http\Controllers\Api\MyCertificationController::class, 'update'])
        ->name('api.my.certifications.update');
    Route::delete('/{certification}', [\App\Http\Controllers\Api\MyCertificationController::class, 'destroy'])
        ->name('api.my.certifications.destroy');
    Route::post('/{certification}/submit', [\App\Http\Controllers\Api\MyCertificationController::class, 'submit'])
        ->name('api.my.certifications.submit');
});
