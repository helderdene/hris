<?php

use App\Http\Controllers\My\Bir2316PageController;
use App\Http\Controllers\My\DocumentRequestPageController;
use App\Http\Controllers\My\MyCertificationPageController;
use App\Http\Controllers\My\MyDtrController;
use App\Http\Controllers\My\MyLoanApplicationController;
use App\Http\Controllers\My\MyLoanController;
use App\Http\Controllers\My\MyScheduleController;
use App\Http\Controllers\My\PayslipPageController;
use App\Http\Controllers\My\SelfServiceDashboardController;
use App\Http\Controllers\MyTrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Employee Self-Service Routes
|--------------------------------------------------------------------------
|
| These routes allow employees to access their own documents and
| certificates, such as BIR 2316 certificates.
|
*/
Route::prefix('my')->group(function () {
    // Self-Service Dashboard
    Route::get('/dashboard', SelfServiceDashboardController::class)
        ->name('my.dashboard');

    // DTR
    Route::get('/dtr', MyDtrController::class)
        ->name('my.dtr');

    // Schedule
    Route::get('/schedule', MyScheduleController::class)
        ->name('my.schedule');

    // Payslips
    Route::get('/payslips', [PayslipPageController::class, 'index'])
        ->name('my.payslips.index');
    Route::get('/payslips/{entry}', [PayslipPageController::class, 'show'])
        ->name('my.payslips.show');
    Route::get('/payslips/{entry}/pdf', [PayslipPageController::class, 'downloadPdf'])
        ->name('my.payslips.pdf');

    // BIR 2316 Certificates
    Route::get('/bir-2316', [Bir2316PageController::class, 'index'])
        ->name('my.bir-2316.index');

    // Document Requests
    Route::get('/document-requests', [DocumentRequestPageController::class, 'index'])
        ->name('my.document-requests.index');

    // Announcements
    Route::get('/announcements', \App\Http\Controllers\My\AnnouncementController::class)
        ->name('my.announcements');

    // Loans Self-Service
    Route::get('/loans', [MyLoanController::class, 'index'])
        ->name('my.loans.index');
    Route::get('/loans/{loan}', [MyLoanController::class, 'show'])
        ->name('my.loans.show');

    // Loan Applications Self-Service
    Route::get('/loan-applications', [MyLoanApplicationController::class, 'index'])
        ->name('my.loan-applications.index');
    Route::get('/loan-applications/create', [MyLoanApplicationController::class, 'create'])
        ->name('my.loan-applications.create');
    Route::get('/loan-applications/{loan_application}', [MyLoanApplicationController::class, 'show'])
        ->name('my.loan-applications.show');

    // Leave Self-Service
    Route::get('/leave', [\App\Http\Controllers\My\MyLeaveController::class, 'index'])
        ->name('my.leave.index');
    Route::get('/leave/calendar', \App\Http\Controllers\My\MyLeaveCalendarController::class)
        ->name('my.leave.calendar');
    Route::get('/leave-approvals', \App\Http\Controllers\My\MyLeaveApprovalController::class)
        ->name('my.leave-approvals.index');

    // Preboarding Self-Service
    Route::get('/preboarding', [\App\Http\Controllers\My\MyPreboardingController::class, 'index'])
        ->name('my.preboarding.index');

    // Onboarding Self-Service
    Route::get('/onboarding', [\App\Http\Controllers\My\MyOnboardingController::class, 'index'])
        ->name('my.onboarding.index');

    // Goals Self-Service
    Route::get('/goals', [\App\Http\Controllers\My\MyGoalController::class, 'index'])
        ->name('my.goals.index');
    Route::get('/goals/create', [\App\Http\Controllers\My\MyGoalController::class, 'create'])
        ->name('my.goals.create');
    Route::get('/goals/{goal}', [\App\Http\Controllers\My\MyGoalController::class, 'show'])
        ->name('my.goals.show');

    // Certifications Self-Service
    Route::get('/certifications', MyCertificationPageController::class)
        ->name('my.certifications.index');

    // Evaluations Self-Service
    Route::get('/evaluations', [\App\Http\Controllers\My\MyEvaluationController::class, 'index'])
        ->name('my.evaluations.index');
    Route::get('/evaluations/{participant}/self', [\App\Http\Controllers\My\MyEvaluationController::class, 'selfEvaluation'])
        ->name('my.evaluations.self');
    Route::get('/evaluations/review/{reviewer}', [\App\Http\Controllers\My\MyEvaluationController::class, 'peerReview'])
        ->name('my.evaluations.review');
    Route::get('/evaluations/{participant}/results', [\App\Http\Controllers\My\MyEvaluationController::class, 'viewResults'])
        ->name('my.evaluations.results');

    // Development Plans Self-Service
    Route::get('/development-plans', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'index'])
        ->name('my.development-plans.index');
    Route::get('/development-plans/create', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'create'])
        ->name('my.development-plans.create');
    Route::get('/development-plans/{developmentPlan}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'show'])
        ->name('my.development-plans.show');

    // Probationary Status Self-Service
    Route::get('/probationary-status', \App\Http\Controllers\My\ProbationaryStatusController::class)
        ->name('my.probationary-status');

    // Training Self-Service (Courses)
    Route::get('/training', [MyTrainingController::class, 'index'])
        ->name('my.training.index');
    Route::get('/training/courses/{course}', [MyTrainingController::class, 'show'])
        ->name('my.training.show');

    // Training Sessions Self-Service
    Route::get('/training/sessions', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'index'])
        ->name('my.training.sessions.index');
    Route::get('/training/sessions/{session}', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'show'])
        ->name('my.training.sessions.show');
    Route::post('/training/sessions/{session}/enroll', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'enroll'])
        ->name('my.training.sessions.enroll');
    Route::post('/training/sessions/{session}/request', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'requestEnrollment'])
        ->name('my.training.sessions.request');
    Route::delete('/training/enrollments/{enrollment}', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'cancelEnrollment'])
        ->name('my.training.enrollments.cancel');
    Route::post('/training/enrollments/{enrollment}/cancel', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'cancelEnrollmentRequest'])
        ->name('my.training.enrollments.cancel-request');
    Route::get('/training/enrollments', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'myEnrollments'])
        ->name('my.training.enrollments.index');
    Route::get('/training/calendar.ics', [\App\Http\Controllers\My\MyTrainingSessionsController::class, 'exportIcal'])
        ->name('my.training.calendar.ical');

    // Compliance Training Self-Service
    Route::get('/compliance', [\App\Http\Controllers\My\MyComplianceController::class, 'index'])
        ->name('my.compliance.index');
    Route::get('/compliance/certificates', [\App\Http\Controllers\My\MyComplianceController::class, 'certificates'])
        ->name('my.compliance.certificates');
    Route::get('/compliance/{assignment}', [\App\Http\Controllers\My\MyComplianceController::class, 'show'])
        ->name('my.compliance.show');
});

/*
|--------------------------------------------------------------------------
| Team Compliance Routes
|--------------------------------------------------------------------------
*/
Route::prefix('team')->group(function () {
    Route::get('/compliance', [\App\Http\Controllers\Team\TeamComplianceController::class, 'index'])
        ->name('team.compliance.index');
});

/*
|--------------------------------------------------------------------------
| Manager Routes
|--------------------------------------------------------------------------
|
| These routes allow managers to view and manage their team's data.
|
*/
Route::prefix('manager')->group(function () {
    // Team Goals
    Route::get('/team-goals', [\App\Http\Controllers\Manager\TeamGoalPageController::class, 'index'])
        ->name('manager.team-goals.index');
    Route::get('/team-goals/approvals', [\App\Http\Controllers\Manager\TeamGoalPageController::class, 'approvals'])
        ->name('manager.team-goals.approvals');

    // Probationary Evaluations
    Route::get('/probationary-evaluations', [\App\Http\Controllers\Manager\ProbationaryEvaluationController::class, 'index'])
        ->name('manager.probationary-evaluations.index');
    Route::get('/probationary-evaluations/{probationary_evaluation}', [\App\Http\Controllers\Manager\ProbationaryEvaluationController::class, 'show'])
        ->name('manager.probationary-evaluations.show');
    Route::put('/probationary-evaluations/{probationary_evaluation}', [\App\Http\Controllers\Manager\ProbationaryEvaluationController::class, 'update'])
        ->name('manager.probationary-evaluations.update');
    Route::post('/probationary-evaluations/{probationary_evaluation}/submit', [\App\Http\Controllers\Manager\ProbationaryEvaluationController::class, 'submit'])
        ->name('manager.probationary-evaluations.submit');
});
