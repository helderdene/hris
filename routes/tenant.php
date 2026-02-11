<?php

/**
 * Tenant Routes
 *
 * Routes defined here are scoped to tenant subdomains (e.g., acme.kasamahr.test).
 * The ResolveTenant and SwitchTenantDatabase middleware will be applied to these routes.
 *
 * Note: Tenant context is shared via HandleInertiaRequests middleware,
 * so routes don't need to pass tenant data explicitly.
 */

use App\Http\Controllers\AnnouncementPageController;
use App\Http\Controllers\Api\AnnouncementController as ApiAnnouncementController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\BirReportController;
use App\Http\Controllers\Api\CompanyDocumentController as ApiCompanyDocumentController;
use App\Http\Controllers\Api\ContributionCalculatorController;
use App\Http\Controllers\Api\DailyTimeRecordController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentRequestController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\EmployeeBir2316Controller;
use App\Http\Controllers\Api\EmployeeCompensationController;
use App\Http\Controllers\Api\EmployeeController as ApiEmployeeController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Controllers\Api\EmployeeLoanController;
use App\Http\Controllers\Api\EmployeeScheduleAssignmentController;
use App\Http\Controllers\Api\HelpArticleController;
use App\Http\Controllers\Api\HelpCategoryController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PagibigContributionController;
use App\Http\Controllers\Api\PagibigReportController;
use App\Http\Controllers\Api\PayrollComputationController;
use App\Http\Controllers\Api\PayrollCycleController;
use App\Http\Controllers\Api\PayrollEntryController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\PerformanceCycleController;
use App\Http\Controllers\Api\PerformanceCycleInstanceController;
use App\Http\Controllers\Api\PerformanceCycleParticipantController;
use App\Http\Controllers\Api\PhilhealthContributionController;
use App\Http\Controllers\Api\PhilhealthReportController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SalaryGradeController;
use App\Http\Controllers\Api\SssContributionController;
use App\Http\Controllers\Api\SssReportController;
use App\Http\Controllers\Api\TenantPayrollSettingsController;
use App\Http\Controllers\Api\TenantUserController;
use App\Http\Controllers\Api\WithholdingTaxController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DtrController;
use App\Http\Controllers\EmployeeAssignmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDashboardController;
use App\Http\Controllers\Help\HelpCenterController;
use App\Http\Controllers\Hr\CertificationPageController;
use App\Http\Controllers\HRAnalyticsDashboardController;
use App\Http\Controllers\LoanApprovalPageController;
use App\Http\Controllers\My\Bir2316PageController;
use App\Http\Controllers\My\DocumentRequestPageController;
use App\Http\Controllers\My\MyCertificationPageController;
use App\Http\Controllers\My\MyDtrController;
use App\Http\Controllers\My\MyLoanApplicationController;
use App\Http\Controllers\My\MyLoanController;
use App\Http\Controllers\My\PayslipPageController;
use App\Http\Controllers\My\SelfServiceDashboardController;
use App\Http\Controllers\MyTrainingController;
use App\Http\Controllers\Organization\CertificationTypePageController;
use App\Http\Controllers\Organization\LeaveBalancePageController;
use App\Http\Controllers\Organization\PayrollPeriodPageController;
use App\Http\Controllers\Organization\PerformanceCyclePageController;
use App\Http\Controllers\Organization\WorkScheduleController as WorkSchedulePageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Payroll\LoanPageController;
use App\Http\Controllers\PerformanceAnalyticsDashboardController;
use App\Http\Controllers\Reports\BirReportPageController;
use App\Http\Controllers\Reports\PagibigReportPageController;
use App\Http\Controllers\Reports\PhilhealthReportPageController;
use App\Http\Controllers\Reports\SssReportPageController;
use App\Http\Controllers\Settings\AuditLogPageController;
use App\Http\Controllers\Settings\HelpAdminPageController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Careers Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::get('/careers', [\App\Http\Controllers\CareersController::class, 'index'])
    ->name('careers.index');

// Public Offer Response (signed URL, no auth required)
Route::get('/offers/{offer}/respond', [\App\Http\Controllers\Recruitment\OfferResponseController::class, 'show'])
    ->name('offers.respond')
    ->middleware('signed');
Route::get('/careers/{slug}', [\App\Http\Controllers\CareersController::class, 'show'])
    ->name('careers.show');
Route::post('/careers/{slug}/apply', [\App\Http\Controllers\Api\PublicApplicationController::class, 'store'])
    ->name('careers.apply');

Route::get('/', function (Request $request) {
    return Inertia::render('TenantDashboard', [
        'justCreated' => $request->boolean('created'),
    ]);
})->name('tenant.home');

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $tenant = app('tenant');
        if ($tenant) {
            $role = $user->getRoleInTenant($tenant);
            if ($role === \App\Enums\TenantUserRole::Employee) {
                return redirect()->route('my.dashboard');
            }
        }
    }

    // Use the ActionCenterDashboardController for HR roles
    return app(\App\Http\Controllers\ActionCenterDashboardController::class)($request);
})->middleware(['auth', 'verified'])->name('tenant.dashboard');

// Logout route for tenant subdomains
Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Redirect to main domain welcome page
    $mainDomain = config('app.main_domain', 'kasamahr.test');
    $scheme = $request->secure() ? 'https' : 'http';

    return Inertia::location("{$scheme}://{$mainDomain}/");
})->name('tenant.logout');

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
|
| These routes are for tenant-specific pages and require authentication.
|
*/

Route::middleware(['auth'])->group(function () {
    // User Management
    // This page allows Admin users to manage tenant members
    Route::get('/users', [UserController::class, 'index'])
        ->name('tenant.users.index');

    // Audit Logs
    // This page allows Admin users to view all model changes for compliance
    Route::get('/settings/audit-logs', AuditLogPageController::class)
        ->name('settings.audit-logs');

    // Help Admin
    // This page allows Super Admins to manage help center content
    Route::get('/settings/help-admin', HelpAdminPageController::class)
        ->name('settings.help-admin');

    /*
    |--------------------------------------------------------------------------
    | Help Center Routes
    |--------------------------------------------------------------------------
    |
    | These routes render the help center pages where all authenticated users
    | can access documentation, search for help articles, and browse categories.
    |
    */
    Route::prefix('help')->name('help.')->group(function () {
        Route::get('/', [HelpCenterController::class, 'index'])->name('index');
        Route::get('/search', [HelpCenterController::class, 'search'])->name('search');
        Route::get('/{categorySlug}', [HelpCenterController::class, 'showCategory'])->name('category');
        Route::get('/{categorySlug}/{articleSlug}', [HelpCenterController::class, 'showArticle'])->name('article');
    });

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
        Route::get('/loans', [LoanPageController::class, 'index'])
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
    | Recruitment Web Routes
    |--------------------------------------------------------------------------
    |
    | These routes render the recruitment pages including job requisitions,
    | pending approvals, and approval history.
    |
    */
    Route::prefix('recruitment')->group(function () {
        // Analytics Dashboard
        Route::get('/analytics', [\App\Http\Controllers\RecruitmentAnalyticsDashboardController::class, 'index'])
            ->name('recruitment.analytics');

        Route::get('/requisitions', [\App\Http\Controllers\Recruitment\JobRequisitionPageController::class, 'index'])
            ->name('recruitment.requisitions.index');
        Route::get('/requisitions/{job_requisition}', [\App\Http\Controllers\Recruitment\JobRequisitionPageController::class, 'show'])
            ->name('recruitment.requisitions.show');
        Route::get('/approvals', [\App\Http\Controllers\Recruitment\JobRequisitionApprovalPageController::class, 'index'])
            ->name('recruitment.approvals.index');

        // Job Postings
        Route::get('/job-postings', [\App\Http\Controllers\Recruitment\JobPostingPageController::class, 'index'])
            ->name('recruitment.job-postings.index');
        Route::get('/job-postings/create', [\App\Http\Controllers\Recruitment\JobPostingPageController::class, 'create'])
            ->name('recruitment.job-postings.create');
        Route::get('/job-postings/{jobPosting}', [\App\Http\Controllers\Recruitment\JobPostingPageController::class, 'show'])
            ->name('recruitment.job-postings.show');
        Route::get('/job-postings/{jobPosting}/edit', [\App\Http\Controllers\Recruitment\JobPostingPageController::class, 'edit'])
            ->name('recruitment.job-postings.edit');

        // Candidates
        Route::get('/candidates', [\App\Http\Controllers\Recruitment\CandidatePageController::class, 'index'])
            ->name('recruitment.candidates.index');
        Route::get('/candidates/create', [\App\Http\Controllers\Recruitment\CandidatePageController::class, 'create'])
            ->name('recruitment.candidates.create');
        Route::get('/candidates/{candidate}', [\App\Http\Controllers\Recruitment\CandidatePageController::class, 'show'])
            ->name('recruitment.candidates.show');
        Route::get('/candidates/{candidate}/edit', [\App\Http\Controllers\Recruitment\CandidatePageController::class, 'edit'])
            ->name('recruitment.candidates.edit');

        // Applications
        Route::get('/job-postings/{jobPosting}/applications', [\App\Http\Controllers\Recruitment\ApplicationPageController::class, 'index'])
            ->name('recruitment.applications.index');
        Route::get('/applications/{jobApplication}', [\App\Http\Controllers\Recruitment\ApplicationPageController::class, 'show'])
            ->name('recruitment.applications.show');

        // Interviews
        Route::get('/interviews', [\App\Http\Controllers\Recruitment\InterviewPageController::class, 'index'])
            ->name('recruitment.interviews.index');
        Route::get('/applications/{jobApplication}/interviews', [\App\Http\Controllers\Recruitment\InterviewPageController::class, 'forApplication'])
            ->name('recruitment.interviews.for-application');
        Route::get('/interviews/{interview}', [\App\Http\Controllers\Recruitment\InterviewPageController::class, 'show'])
            ->name('recruitment.interviews.show');

        // Offer Templates
        Route::get('/offer-templates', [\App\Http\Controllers\Recruitment\OfferTemplatePageController::class, 'index'])
            ->name('recruitment.offer-templates.index');
        Route::get('/offer-templates/create', [\App\Http\Controllers\Recruitment\OfferTemplatePageController::class, 'create'])
            ->name('recruitment.offer-templates.create');
        Route::get('/offer-templates/{offerTemplate}/edit', [\App\Http\Controllers\Recruitment\OfferTemplatePageController::class, 'edit'])
            ->name('recruitment.offer-templates.edit');

        // Offers
        Route::get('/offers', [\App\Http\Controllers\Recruitment\OfferPageController::class, 'index'])
            ->name('recruitment.offers.index');
        Route::get('/offers/create', [\App\Http\Controllers\Recruitment\OfferPageController::class, 'create'])
            ->name('recruitment.offers.create');
        Route::get('/offers/{offer}', [\App\Http\Controllers\Recruitment\OfferPageController::class, 'show'])
            ->name('recruitment.offers.show');
    });

    /*
    |--------------------------------------------------------------------------
    | HR Document Request Management Web Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('hr')->group(function () {
        Route::get('/document-requests', [\App\Http\Controllers\Hr\DocumentRequestManagementController::class, 'index'])
            ->name('hr.document-requests.index');

        // Probationary Evaluations
        Route::get('/probationary-evaluations', [\App\Http\Controllers\Hr\ProbationaryEvaluationController::class, 'index'])
            ->name('hr.probationary-evaluations.index');
        Route::get('/probationary-evaluations/{probationary_evaluation}', [\App\Http\Controllers\Hr\ProbationaryEvaluationController::class, 'show'])
            ->name('hr.probationary-evaluations.show');
        Route::post('/probationary-evaluations/{probationary_evaluation}/approve', [\App\Http\Controllers\Hr\ProbationaryEvaluationController::class, 'approve'])
            ->name('hr.probationary-evaluations.approve');
        Route::post('/probationary-evaluations/{probationary_evaluation}/reject', [\App\Http\Controllers\Hr\ProbationaryEvaluationController::class, 'reject'])
            ->name('hr.probationary-evaluations.reject');
        Route::post('/probationary-evaluations/{probationary_evaluation}/request-revision', [\App\Http\Controllers\Hr\ProbationaryEvaluationController::class, 'requestRevision'])
            ->name('hr.probationary-evaluations.request-revision');

        // Certifications (HR View)
        Route::get('/certifications', [CertificationPageController::class, 'index'])
            ->name('hr.certifications.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Pre-boarding Management Web Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/preboarding', [\App\Http\Controllers\PreboardingPageController::class, 'index'])
        ->name('preboarding.index');
    Route::get('/preboarding/{checklist}', [\App\Http\Controllers\PreboardingPageController::class, 'show'])
        ->name('preboarding.show');

    /*
    |--------------------------------------------------------------------------
    | Onboarding Management Web Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/onboarding', [\App\Http\Controllers\OnboardingPageController::class, 'index'])
        ->name('onboarding.index');
    Route::get('/onboarding/{checklist}', [\App\Http\Controllers\OnboardingPageController::class, 'show'])
        ->name('onboarding.show');

    // Onboarding Tasks (role-based view for IT/Admin/HR)
    Route::get('/onboarding-tasks', [\App\Http\Controllers\OnboardingTasksPageController::class, 'index'])
        ->name('onboarding.tasks.index');

    // Onboarding Template Management
    Route::get('/onboarding-templates', [\App\Http\Controllers\OnboardingTemplatePageController::class, 'index'])
        ->name('onboarding.templates.index');
    Route::get('/onboarding-templates/create', [\App\Http\Controllers\OnboardingTemplatePageController::class, 'create'])
        ->name('onboarding.templates.create');
    Route::get('/onboarding-templates/{template}/edit', [\App\Http\Controllers\OnboardingTemplatePageController::class, 'edit'])
        ->name('onboarding.templates.edit');

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
});

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| These API routes are for tenant-specific operations and require
| authentication. The tenant context is already resolved by the
| 'tenant' middleware group applied to all tenant subdomain routes.
|
| Re-Authentication Pattern:
| Sensitive actions that modify user access or roles require password
| confirmation via the `password.confirm` middleware. This includes:
| - User role changes (PATCH /api/users/{user})
| - User deactivation/removal (DELETE /api/users/{user})
|
| Password confirmation is valid for 3 hours (configured in config/auth.php).
| See docs/re-authentication.md for detailed implementation guidance.
|
*/

Route::prefix('api')->middleware(['auth'])->group(function () {
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

    // User Management API
    // These endpoints allow Admin users to manage tenant members
    Route::get('/users', [TenantUserController::class, 'index'])
        ->name('api.tenant.users.index');

    Route::post('/users/invite', [TenantUserController::class, 'invite'])
        ->name('api.tenant.users.invite');

    // Sensitive actions requiring password confirmation
    // These routes use the password.confirm middleware to ensure
    // the user has recently verified their identity
    Route::patch('/users/{user}', [TenantUserController::class, 'update'])
        ->middleware('password.confirm')
        ->name('api.tenant.users.update');

    Route::delete('/users/{user}', [TenantUserController::class, 'destroy'])
        ->middleware('password.confirm')
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

    /*
    |--------------------------------------------------------------------------
    | Employee Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users (Admin, HR Manager) to manage
    | employee records including personal info, employment details, and
    | government IDs.
    |
    */

    Route::get('/employees', [ApiEmployeeController::class, 'index'])
        ->name('api.employees.index');
    Route::post('/employees', [ApiEmployeeController::class, 'store'])
        ->name('api.employees.store');
    Route::get('/employees/{employee}', [ApiEmployeeController::class, 'show'])
        ->name('api.employees.show');
    Route::put('/employees/{employee}', [ApiEmployeeController::class, 'update'])
        ->name('api.employees.update');
    Route::delete('/employees/{employee}', [ApiEmployeeController::class, 'destroy'])
        ->name('api.employees.destroy');

    /*
    |--------------------------------------------------------------------------
    | Employee Biometric Sync API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to view sync status and trigger
    | synchronization of employee profile photos to biometric devices.
    |
    */

    Route::prefix('employees/{employee}')->group(function () {
        Route::get('/sync-status', [BiometricSyncController::class, 'employeeSyncStatus'])
            ->name('api.employees.sync-status');
        Route::get('/verify-devices', [BiometricSyncController::class, 'verifyEmployeeDevices'])
            ->name('api.employees.verify-devices');
        Route::post('/sync-to-devices', [BiometricSyncController::class, 'syncEmployeeToDevices'])
            ->name('api.employees.sync-to-devices');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Assignment Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage employee assignments
    | (position, department, work location, supervisor) and view assignment
    | history.
    |
    */

    Route::get('/employees/{employee}/assignments', [EmployeeAssignmentController::class, 'index'])
        ->name('api.employees.assignments.index');
    Route::post('/employees/{employee}/assignments', [EmployeeAssignmentController::class, 'store'])
        ->name('api.employees.assignments.store');

    /*
    |--------------------------------------------------------------------------
    | Employee Compensation Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage employee compensation
    | (basic pay, pay type, bank account details) and view compensation
    | history.
    |
    */

    Route::get('/employees/{employee}/compensation', [EmployeeCompensationController::class, 'index'])
        ->name('api.employees.compensation.index');
    Route::post('/employees/{employee}/compensation', [EmployeeCompensationController::class, 'store'])
        ->name('api.employees.compensation.store');

    /*
    |--------------------------------------------------------------------------
    | Employee Document Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage employee documents
    | including uploading, viewing, versioning, and downloading documents.
    |
    */

    Route::get('/employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])
        ->name('api.employees.documents.index');
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('api.employees.documents.store');
    Route::get('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'show'])
        ->name('api.employees.documents.show');
    Route::delete('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
        ->name('api.employees.documents.destroy');

    /*
    |--------------------------------------------------------------------------
    | Company Document Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage company-wide documents
    | (policies, handbooks, announcements). All tenant users can view these
    | documents, but only HR staff can upload and delete them.
    |
    */

    Route::get('/company-documents', [ApiCompanyDocumentController::class, 'index'])
        ->name('api.company-documents.index');
    Route::post('/company-documents', [ApiCompanyDocumentController::class, 'store'])
        ->name('api.company-documents.store');
    Route::get('/company-documents/{document}', [ApiCompanyDocumentController::class, 'show'])
        ->name('api.company-documents.show');
    Route::delete('/company-documents/{document}', [ApiCompanyDocumentController::class, 'destroy'])
        ->name('api.company-documents.destroy');

    /*
    |--------------------------------------------------------------------------
    | Document Version Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to upload new versions of
    | existing documents, download specific versions, and preview documents
    | inline in the browser. These routes are shared between employee
    | documents and company documents.
    |
    */

    Route::post('/documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->name('api.documents.versions.store');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentVersionController::class, 'download'])
        ->name('api.documents.versions.download');
    Route::get('/documents/{document}/versions/{version}/preview', [DocumentVersionController::class, 'preview'])
        ->name('api.documents.versions.preview');

    /*
    |--------------------------------------------------------------------------
    | Document Category Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage document categories.
    | All users can view categories, but only HR staff can create or delete
    | custom categories. Predefined categories cannot be deleted.
    |
    */

    Route::get('/document-categories', [DocumentCategoryController::class, 'index'])
        ->name('api.document-categories.index');
    Route::post('/document-categories', [DocumentCategoryController::class, 'store'])
        ->name('api.document-categories.store');
    Route::delete('/document-categories/{category}', [DocumentCategoryController::class, 'destroy'])
        ->name('api.document-categories.destroy');

    /*
    |--------------------------------------------------------------------------
    | Announcement Management API
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users (Admin, HR Manager) to manage
    | company announcements including creating, updating, and deleting
    | announcements with publish scheduling and pinning support.
    |
    */

    Route::get('/announcements', [ApiAnnouncementController::class, 'index'])
        ->name('api.announcements.index');
    Route::post('/announcements', [ApiAnnouncementController::class, 'store'])
        ->name('api.announcements.store');
    Route::put('/announcements/{announcement}', [ApiAnnouncementController::class, 'update'])
        ->name('api.announcements.update');
    Route::delete('/announcements/{announcement}', [ApiAnnouncementController::class, 'destroy'])
        ->name('api.announcements.destroy');

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

    /*
    |--------------------------------------------------------------------------
    | Performance Management API (KPIs)
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage KPI templates,
    | assignments, and progress tracking for performance evaluations.
    |
    */

    Route::prefix('performance')->group(function () {
        // KPI Templates
        Route::get('/kpi-templates', [\App\Http\Controllers\Api\KpiTemplateController::class, 'index'])
            ->name('api.performance.kpi-templates.index');
        Route::post('/kpi-templates', [\App\Http\Controllers\Api\KpiTemplateController::class, 'store'])
            ->name('api.performance.kpi-templates.store');
        Route::get('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'show'])
            ->name('api.performance.kpi-templates.show');
        Route::put('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'update'])
            ->name('api.performance.kpi-templates.update');
        Route::delete('/kpi-templates/{kpiTemplate}', [\App\Http\Controllers\Api\KpiTemplateController::class, 'destroy'])
            ->name('api.performance.kpi-templates.destroy');

        // KPI Assignments
        Route::get('/kpi-assignments', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'index'])
            ->name('api.performance.kpi-assignments.index');
        Route::post('/kpi-assignments', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'store'])
            ->name('api.performance.kpi-assignments.store');
        Route::post('/kpi-assignments/bulk', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'bulkAssign'])
            ->name('api.performance.kpi-assignments.bulk');
        Route::get('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'show'])
            ->name('api.performance.kpi-assignments.show');
        Route::put('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'update'])
            ->name('api.performance.kpi-assignments.update');
        Route::delete('/kpi-assignments/{kpiAssignment}', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'destroy'])
            ->name('api.performance.kpi-assignments.destroy');
        Route::post('/kpi-assignments/{kpiAssignment}/progress', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'recordProgress'])
            ->name('api.performance.kpi-assignments.record-progress');
        Route::post('/kpi-assignments/{kpiAssignment}/complete', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'complete'])
            ->name('api.performance.kpi-assignments.complete');
        Route::get('/kpi-assignments/{kpiAssignment}/progress-history', [\App\Http\Controllers\Api\KpiAssignmentController::class, 'progressHistory'])
            ->name('api.performance.kpi-assignments.progress-history');

        // Participant KPIs Summary
        Route::get('/participants/{participant}/kpis', [\App\Http\Controllers\Api\ParticipantKpiController::class, 'index'])
            ->name('api.performance.participants.kpis');

        // Competencies
        Route::get('/competencies', [\App\Http\Controllers\Api\CompetencyController::class, 'index'])
            ->name('api.performance.competencies.index');
        Route::post('/competencies', [\App\Http\Controllers\Api\CompetencyController::class, 'store'])
            ->name('api.performance.competencies.store');
        Route::get('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'show'])
            ->name('api.performance.competencies.show');
        Route::put('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'update'])
            ->name('api.performance.competencies.update');
        Route::delete('/competencies/{competency}', [\App\Http\Controllers\Api\CompetencyController::class, 'destroy'])
            ->name('api.performance.competencies.destroy');

        // Proficiency Levels
        Route::get('/proficiency-levels', [\App\Http\Controllers\Api\ProficiencyLevelController::class, 'index'])
            ->name('api.performance.proficiency-levels.index');
        Route::get('/proficiency-levels/{proficiencyLevel}', [\App\Http\Controllers\Api\ProficiencyLevelController::class, 'show'])
            ->name('api.performance.proficiency-levels.show');

        // Position Competencies (Matrix)
        Route::get('/position-competencies', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'index'])
            ->name('api.performance.position-competencies.index');
        Route::post('/position-competencies', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'store'])
            ->name('api.performance.position-competencies.store');
        Route::post('/position-competencies/batch', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'batchUpdate'])
            ->name('api.performance.position-competencies.batch');
        Route::get('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'show'])
            ->name('api.performance.position-competencies.show');
        Route::put('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'update'])
            ->name('api.performance.position-competencies.update');
        Route::delete('/position-competencies/{positionCompetency}', [\App\Http\Controllers\Api\PositionCompetencyController::class, 'destroy'])
            ->name('api.performance.position-competencies.destroy');

        // Competency Evaluations
        Route::get('/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'index'])
            ->name('api.performance.competency-evaluations.index');
        Route::post('/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'store'])
            ->name('api.performance.competency-evaluations.store');
        Route::get('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'show'])
            ->name('api.performance.competency-evaluations.show');
        Route::put('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'update'])
            ->name('api.performance.competency-evaluations.update');
        Route::delete('/competency-evaluations/{competencyEvaluation}', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'destroy'])
            ->name('api.performance.competency-evaluations.destroy');
        Route::post('/competency-evaluations/{competencyEvaluation}/self-rating', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'submitSelfRating'])
            ->name('api.performance.competency-evaluations.self-rating');
        Route::post('/competency-evaluations/{competencyEvaluation}/manager-rating', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'submitManagerRating'])
            ->name('api.performance.competency-evaluations.manager-rating');

        // Participant Competency Evaluations
        Route::get('/participants/{participant}/competency-evaluations', [\App\Http\Controllers\Api\CompetencyEvaluationController::class, 'participantEvaluations'])
            ->name('api.performance.participants.competency-evaluations');

        // Goals Management
        Route::apiResource('goals', \App\Http\Controllers\Api\GoalController::class)
            ->names([
                'index' => 'api.performance.goals.index',
                'store' => 'api.performance.goals.store',
                'show' => 'api.performance.goals.show',
                'update' => 'api.performance.goals.update',
                'destroy' => 'api.performance.goals.destroy',
            ]);
        Route::post('/goals/{goal}/submit-approval', [\App\Http\Controllers\Api\GoalController::class, 'submitForApproval'])
            ->name('api.performance.goals.submit-approval');
        Route::post('/goals/{goal}/approve', [\App\Http\Controllers\Api\GoalController::class, 'approve'])
            ->name('api.performance.goals.approve');
        Route::post('/goals/{goal}/reject', [\App\Http\Controllers\Api\GoalController::class, 'reject'])
            ->name('api.performance.goals.reject');
        Route::post('/goals/{goal}/progress', [\App\Http\Controllers\Api\GoalController::class, 'updateProgress'])
            ->name('api.performance.goals.progress');
        Route::post('/goals/{goal}/complete', [\App\Http\Controllers\Api\GoalController::class, 'complete'])
            ->name('api.performance.goals.complete');

        // Development Plans Management
        Route::post('/development-plans/{developmentPlan}/approve', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'approve'])
            ->name('api.performance.development-plans.approve');
        Route::post('/development-plans/{developmentPlan}/reject', [\App\Http\Controllers\Performance\DevelopmentPlanController::class, 'reject'])
            ->name('api.performance.development-plans.reject');

        // Goal Key Results (nested under goals)
        Route::get('/goals/{goal}/key-results', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'index'])
            ->name('api.performance.goals.key-results.index');
        Route::post('/goals/{goal}/key-results', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'store'])
            ->name('api.performance.goals.key-results.store');
        Route::get('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'show'])
            ->name('api.performance.goals.key-results.show');
        Route::put('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'update'])
            ->name('api.performance.goals.key-results.update');
        Route::delete('/goals/{goal}/key-results/{keyResult}', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'destroy'])
            ->name('api.performance.goals.key-results.destroy');
        Route::post('/goals/{goal}/key-results/{keyResult}/progress', [\App\Http\Controllers\Api\GoalKeyResultController::class, 'recordProgress'])
            ->name('api.performance.goals.key-results.progress');

        // Goal Milestones (nested under goals)
        Route::get('/goals/{goal}/milestones', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'index'])
            ->name('api.performance.goals.milestones.index');
        Route::post('/goals/{goal}/milestones', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'store'])
            ->name('api.performance.goals.milestones.store');
        Route::get('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'show'])
            ->name('api.performance.goals.milestones.show');
        Route::put('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'update'])
            ->name('api.performance.goals.milestones.update');
        Route::delete('/goals/{goal}/milestones/{milestone}', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'destroy'])
            ->name('api.performance.goals.milestones.destroy');
        Route::post('/goals/{goal}/milestones/{milestone}/toggle', [\App\Http\Controllers\Api\GoalMilestoneController::class, 'toggleComplete'])
            ->name('api.performance.goals.milestones.toggle');

        // Goal Comments (nested under goals)
        Route::get('/goals/{goal}/comments', [\App\Http\Controllers\Api\GoalCommentController::class, 'index'])
            ->name('api.performance.goals.comments.index');
        Route::post('/goals/{goal}/comments', [\App\Http\Controllers\Api\GoalCommentController::class, 'store'])
            ->name('api.performance.goals.comments.store');
        Route::get('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'show'])
            ->name('api.performance.goals.comments.show');
        Route::put('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'update'])
            ->name('api.performance.goals.comments.update');
        Route::delete('/goals/{goal}/comments/{comment}', [\App\Http\Controllers\Api\GoalCommentController::class, 'destroy'])
            ->name('api.performance.goals.comments.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Goal Self-Service API Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('my')->group(function () {
        Route::get('/goals', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'index'])
            ->name('api.my.goals.index');
        Route::post('/goals', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'store'])
            ->name('api.my.goals.store');
        Route::get('/goals/statistics', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'statistics'])
            ->name('api.my.goals.statistics');
        Route::get('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'show'])
            ->name('api.my.goals.show');
        Route::put('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'update'])
            ->name('api.my.goals.update');
        Route::post('/goals/{goal}/progress', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'updateProgress'])
            ->name('api.my.goals.progress');
        Route::post('/goals/{goal}/submit-approval', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'submitForApproval'])
            ->name('api.my.goals.submit-approval');
        Route::delete('/goals/{goal}', [\App\Http\Controllers\Api\EmployeeGoalController::class, 'destroy'])
            ->name('api.my.goals.destroy');

        // Development Plans Self-Service API
        Route::post('/development-plans', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'store'])
            ->name('api.my.development-plans.store');
        Route::put('/development-plans/{developmentPlan}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'update'])
            ->name('api.my.development-plans.update');
        Route::post('/development-plans/{developmentPlan}/submit', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'submit'])
            ->name('api.my.development-plans.submit');
        Route::post('/development-plans/{developmentPlan}/items', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addItem'])
            ->name('api.my.development-plans.add-item');
        Route::put('/development-plans/{developmentPlan}/items/{item}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'updateItem'])
            ->name('api.my.development-plans.update-item');
        Route::post('/development-plans/{developmentPlan}/items/{item}/activities', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addActivity'])
            ->name('api.my.development-plans.add-activity');
        Route::put('/development-plans/activities/{activity}', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'updateActivity'])
            ->name('api.my.development-plans.update-activity');
        Route::post('/development-plans/activities/{activity}/complete', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'completeActivity'])
            ->name('api.my.development-plans.complete-activity');
        Route::post('/development-plans/{developmentPlan}/check-ins', [\App\Http\Controllers\My\MyDevelopmentPlanController::class, 'addCheckIn'])
            ->name('api.my.development-plans.add-check-in');
    });

    /*
    |--------------------------------------------------------------------------
    | Manager Goal API Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('manager')->group(function () {
        Route::get('/team-goals', [\App\Http\Controllers\Api\TeamGoalController::class, 'index'])
            ->name('api.manager.team-goals.index');
        Route::get('/team-goals/pending-approvals', [\App\Http\Controllers\Api\TeamGoalController::class, 'pendingApprovals'])
            ->name('api.manager.team-goals.pending-approvals');
        Route::get('/team-goals/summary', [\App\Http\Controllers\Api\TeamGoalController::class, 'summary'])
            ->name('api.manager.team-goals.summary');
        Route::post('/team-goals/{goal}/approve', [\App\Http\Controllers\Api\TeamGoalController::class, 'approve'])
            ->name('api.manager.team-goals.approve');
        Route::post('/team-goals/{goal}/reject', [\App\Http\Controllers\Api\TeamGoalController::class, 'reject'])
            ->name('api.manager.team-goals.reject');
    });

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

        // Job requisition approval actions
        Route::post('/requisitions/{jobRequisition}/approve', [\App\Http\Controllers\Api\InlineApprovalController::class, 'approveRequisition'])
            ->name('api.action-center.requisitions.approve');
        Route::post('/requisitions/{jobRequisition}/reject', [\App\Http\Controllers\Api\InlineApprovalController::class, 'rejectRequisition'])
            ->name('api.action-center.requisitions.reject');
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

    /*
    |--------------------------------------------------------------------------
    | Job Posting Management API Routes
    |--------------------------------------------------------------------------
    |
    | These endpoints allow authorized users to manage job postings including
    | creating, updating, publishing, closing, and archiving postings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Candidate Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('candidates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CandidateController::class, 'index'])
            ->name('api.candidates.index');
        Route::post('/', [\App\Http\Controllers\Api\CandidateController::class, 'store'])
            ->name('api.candidates.store');
        Route::post('/check-duplicates', [\App\Http\Controllers\Api\CandidateController::class, 'checkDuplicates'])
            ->name('api.candidates.check-duplicates');
        Route::get('/{candidate}', [\App\Http\Controllers\Api\CandidateController::class, 'show'])
            ->name('api.candidates.show');
        Route::put('/{candidate}', [\App\Http\Controllers\Api\CandidateController::class, 'update'])
            ->name('api.candidates.update');
        Route::delete('/{candidate}', [\App\Http\Controllers\Api\CandidateController::class, 'destroy'])
            ->name('api.candidates.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Job Application Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/job-postings/{jobPostingId}/applications', [\App\Http\Controllers\Api\JobApplicationController::class, 'index'])
        ->name('api.job-applications.index');
    Route::post('/applications', [\App\Http\Controllers\Api\JobApplicationController::class, 'store'])
        ->name('api.job-applications.store');
    Route::get('/applications/{jobApplication}', [\App\Http\Controllers\Api\JobApplicationController::class, 'show'])
        ->name('api.job-applications.show');
    Route::patch('/applications/{jobApplication}/status', [\App\Http\Controllers\Api\JobApplicationController::class, 'updateStatus'])
        ->name('api.job-applications.update-status');
    Route::delete('/applications/{jobApplication}', [\App\Http\Controllers\Api\JobApplicationController::class, 'destroy'])
        ->name('api.job-applications.destroy');

    /*
    |--------------------------------------------------------------------------
    | Interview Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/applications/{jobApplication}/interviews', [\App\Http\Controllers\Api\InterviewController::class, 'index'])
        ->name('api.interviews.index');
    Route::post('/applications/{jobApplication}/interviews', [\App\Http\Controllers\Api\InterviewController::class, 'store'])
        ->name('api.interviews.store');
    Route::put('/interviews/{interview}', [\App\Http\Controllers\Api\InterviewController::class, 'update'])
        ->name('api.interviews.update');
    Route::post('/interviews/{interview}/cancel', [\App\Http\Controllers\Api\InterviewController::class, 'cancel'])
        ->name('api.interviews.cancel');
    Route::post('/interviews/{interview}/send-invitations', [\App\Http\Controllers\Api\InterviewController::class, 'sendInvitations'])
        ->name('api.interviews.send-invitations');
    Route::get('/interviews/{interview}/calendar.ics', [\App\Http\Controllers\Api\InterviewCalendarController::class, 'download'])
        ->name('api.interviews.calendar');
    Route::get('/interviews/{interview}/feedback', [\App\Http\Controllers\Api\InterviewFeedbackController::class, 'index'])
        ->name('api.interviews.feedback.index');
    Route::post('/interviews/{interview}/feedback', [\App\Http\Controllers\Api\InterviewFeedbackController::class, 'store'])
        ->name('api.interviews.feedback.store');

    /*
    |--------------------------------------------------------------------------
    | Assessment Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/applications/{jobApplication}/assessments', [\App\Http\Controllers\Api\AssessmentController::class, 'index'])
        ->name('api.assessments.index');
    Route::post('/applications/{jobApplication}/assessments', [\App\Http\Controllers\Api\AssessmentController::class, 'store'])
        ->name('api.assessments.store');
    Route::put('/assessments/{assessment}', [\App\Http\Controllers\Api\AssessmentController::class, 'update'])
        ->name('api.assessments.update');
    Route::delete('/assessments/{assessment}', [\App\Http\Controllers\Api\AssessmentController::class, 'destroy'])
        ->name('api.assessments.destroy');

    /*
    |--------------------------------------------------------------------------
    | Background Check Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/applications/{jobApplication}/background-checks', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'index'])
        ->name('api.background-checks.index');
    Route::post('/applications/{jobApplication}/background-checks', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'store'])
        ->name('api.background-checks.store');
    Route::put('/background-checks/{backgroundCheck}', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'update'])
        ->name('api.background-checks.update');
    Route::delete('/background-checks/{backgroundCheck}', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'destroy'])
        ->name('api.background-checks.destroy');
    Route::post('/background-checks/{backgroundCheck}/documents', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'uploadDocument'])
        ->name('api.background-checks.documents.store');
    Route::delete('/background-check-documents/{backgroundCheckDocument}', [\App\Http\Controllers\Api\BackgroundCheckController::class, 'deleteDocument'])
        ->name('api.background-check-documents.destroy');

    /*
    |--------------------------------------------------------------------------
    | Reference Check Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/applications/{jobApplication}/reference-checks', [\App\Http\Controllers\Api\ReferenceCheckController::class, 'index'])
        ->name('api.reference-checks.index');
    Route::post('/applications/{jobApplication}/reference-checks', [\App\Http\Controllers\Api\ReferenceCheckController::class, 'store'])
        ->name('api.reference-checks.store');
    Route::put('/reference-checks/{referenceCheck}', [\App\Http\Controllers\Api\ReferenceCheckController::class, 'update'])
        ->name('api.reference-checks.update');
    Route::delete('/reference-checks/{referenceCheck}', [\App\Http\Controllers\Api\ReferenceCheckController::class, 'destroy'])
        ->name('api.reference-checks.destroy');
    Route::post('/reference-checks/{referenceCheck}/mark-contacted', [\App\Http\Controllers\Api\ReferenceCheckController::class, 'markContacted'])
        ->name('api.reference-checks.mark-contacted');

    /*
    |--------------------------------------------------------------------------
    | Offer Template Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('offer-templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\OfferTemplateController::class, 'index'])
            ->name('api.offer-templates.index');
        Route::post('/', [\App\Http\Controllers\Api\OfferTemplateController::class, 'store'])
            ->name('api.offer-templates.store');
        Route::get('/{offerTemplate}', [\App\Http\Controllers\Api\OfferTemplateController::class, 'show'])
            ->name('api.offer-templates.show');
        Route::put('/{offerTemplate}', [\App\Http\Controllers\Api\OfferTemplateController::class, 'update'])
            ->name('api.offer-templates.update');
        Route::delete('/{offerTemplate}', [\App\Http\Controllers\Api\OfferTemplateController::class, 'destroy'])
            ->name('api.offer-templates.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Offer Management API Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('offers')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\OfferController::class, 'index'])
            ->name('api.offers.index');
        Route::post('/', [\App\Http\Controllers\Api\OfferController::class, 'store'])
            ->name('api.offers.store');
        Route::get('/{offer}', [\App\Http\Controllers\Api\OfferController::class, 'show'])
            ->name('api.offers.show');
        Route::put('/{offer}', [\App\Http\Controllers\Api\OfferController::class, 'update'])
            ->name('api.offers.update');
        Route::delete('/{offer}', [\App\Http\Controllers\Api\OfferController::class, 'destroy'])
            ->name('api.offers.destroy');
        Route::post('/{offer}/send', [\App\Http\Controllers\Api\OfferController::class, 'send'])
            ->name('api.offers.send');
        Route::post('/{offer}/accept', [\App\Http\Controllers\Api\OfferController::class, 'accept'])
            ->name('api.offers.accept');
        Route::post('/{offer}/decline', [\App\Http\Controllers\Api\OfferController::class, 'decline'])
            ->name('api.offers.decline');
        Route::post('/{offer}/revoke', [\App\Http\Controllers\Api\OfferController::class, 'revoke'])
            ->name('api.offers.revoke');
        Route::get('/{offer}/pdf', [\App\Http\Controllers\Api\OfferController::class, 'downloadPdf'])
            ->name('api.offers.download-pdf');
        Route::get('/{offer}/preview', [\App\Http\Controllers\Api\OfferController::class, 'preview'])
            ->name('api.offers.preview');
    });

    Route::prefix('job-postings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\JobPostingController::class, 'index'])
            ->name('api.job-postings.index');
        Route::post('/', [\App\Http\Controllers\Api\JobPostingController::class, 'store'])
            ->name('api.job-postings.store');
        Route::get('/{job_posting}', [\App\Http\Controllers\Api\JobPostingController::class, 'show'])
            ->name('api.job-postings.show');
        Route::put('/{job_posting}', [\App\Http\Controllers\Api\JobPostingController::class, 'update'])
            ->name('api.job-postings.update');
        Route::delete('/{job_posting}', [\App\Http\Controllers\Api\JobPostingController::class, 'destroy'])
            ->name('api.job-postings.destroy');

        // Workflow actions
        Route::post('/{job_posting}/publish', [\App\Http\Controllers\Api\JobPostingController::class, 'publish'])
            ->name('api.job-postings.publish');
        Route::post('/{job_posting}/close', [\App\Http\Controllers\Api\JobPostingController::class, 'close'])
            ->name('api.job-postings.close');
        Route::post('/{job_posting}/archive', [\App\Http\Controllers\Api\JobPostingController::class, 'archive'])
            ->name('api.job-postings.archive');
    });

    /*
    |--------------------------------------------------------------------------
    | Training Course Catalog API Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('training')->group(function () {
        // Course Categories
        Route::get('/categories', [\App\Http\Controllers\Api\CourseCategoryController::class, 'index'])
            ->name('api.training.categories.index');
        Route::post('/categories', [\App\Http\Controllers\Api\CourseCategoryController::class, 'store'])
            ->name('api.training.categories.store');
        Route::get('/categories/{category}', [\App\Http\Controllers\Api\CourseCategoryController::class, 'show'])
            ->name('api.training.categories.show');
        Route::put('/categories/{category}', [\App\Http\Controllers\Api\CourseCategoryController::class, 'update'])
            ->name('api.training.categories.update');
        Route::delete('/categories/{category}', [\App\Http\Controllers\Api\CourseCategoryController::class, 'destroy'])
            ->name('api.training.categories.destroy');

        // Courses
        Route::get('/courses', [\App\Http\Controllers\Api\CourseController::class, 'index'])
            ->name('api.training.courses.index');
        Route::post('/courses', [\App\Http\Controllers\Api\CourseController::class, 'store'])
            ->name('api.training.courses.store');
        Route::get('/courses/{course}', [\App\Http\Controllers\Api\CourseController::class, 'show'])
            ->name('api.training.courses.show');
        Route::put('/courses/{course}', [\App\Http\Controllers\Api\CourseController::class, 'update'])
            ->name('api.training.courses.update');
        Route::delete('/courses/{course}', [\App\Http\Controllers\Api\CourseController::class, 'destroy'])
            ->name('api.training.courses.destroy');

        // Course Workflow Actions
        Route::post('/courses/{course}/publish', [\App\Http\Controllers\Api\CourseController::class, 'publish'])
            ->name('api.training.courses.publish');
        Route::post('/courses/{course}/archive', [\App\Http\Controllers\Api\CourseController::class, 'archive'])
            ->name('api.training.courses.archive');
        Route::post('/courses/{course}/duplicate', [\App\Http\Controllers\Api\CourseController::class, 'duplicate'])
            ->name('api.training.courses.duplicate');

        // Course Materials
        Route::get('/courses/{course}/materials', [\App\Http\Controllers\Api\CourseMaterialController::class, 'index'])
            ->name('api.training.courses.materials.index');
        Route::post('/courses/{course}/materials', [\App\Http\Controllers\Api\CourseMaterialController::class, 'store'])
            ->name('api.training.courses.materials.store');
        Route::put('/courses/{course}/materials/{material}', [\App\Http\Controllers\Api\CourseMaterialController::class, 'update'])
            ->name('api.training.courses.materials.update');
        Route::delete('/courses/{course}/materials/{material}', [\App\Http\Controllers\Api\CourseMaterialController::class, 'destroy'])
            ->name('api.training.courses.materials.destroy');
        Route::post('/courses/{course}/materials/reorder', [\App\Http\Controllers\Api\CourseMaterialController::class, 'reorder'])
            ->name('api.training.courses.materials.reorder');

        // Material Download (standalone route for download URL generation)
        Route::get('/materials/{material}/download', [\App\Http\Controllers\Api\CourseMaterialController::class, 'download'])
            ->name('api.training.materials.download');

        // Training Sessions
        Route::get('/sessions', [\App\Http\Controllers\Api\TrainingSessionController::class, 'index'])
            ->name('api.training.sessions.index');
        Route::post('/sessions', [\App\Http\Controllers\Api\TrainingSessionController::class, 'store'])
            ->name('api.training.sessions.store');
        Route::get('/sessions/{session}', [\App\Http\Controllers\Api\TrainingSessionController::class, 'show'])
            ->name('api.training.sessions.show');
        Route::put('/sessions/{session}', [\App\Http\Controllers\Api\TrainingSessionController::class, 'update'])
            ->name('api.training.sessions.update');
        Route::delete('/sessions/{session}', [\App\Http\Controllers\Api\TrainingSessionController::class, 'destroy'])
            ->name('api.training.sessions.destroy');
        Route::post('/sessions/{session}/publish', [\App\Http\Controllers\Api\TrainingSessionController::class, 'publish'])
            ->name('api.training.sessions.publish');
        Route::post('/sessions/{session}/cancel', [\App\Http\Controllers\Api\TrainingSessionController::class, 'cancel'])
            ->name('api.training.sessions.cancel');

        // Training Session Enrollments
        Route::get('/sessions/{session}/enrollments', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'index'])
            ->name('api.training.sessions.enrollments.index');
        Route::post('/enrollments', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'store'])
            ->name('api.training.enrollments.store');
        Route::get('/enrollments/{enrollment}', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'show'])
            ->name('api.training.enrollments.show');
        Route::delete('/enrollments/{enrollment}', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'destroy'])
            ->name('api.training.enrollments.destroy');
        Route::post('/enrollments/{enrollment}/attended', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'markAttended'])
            ->name('api.training.enrollments.attended');
        Route::post('/enrollments/{enrollment}/no-show', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'markNoShow'])
            ->name('api.training.enrollments.no-show');
        Route::post('/sessions/{session}/bulk-enroll', [\App\Http\Controllers\Api\TrainingEnrollmentController::class, 'bulkEnroll'])
            ->name('api.training.sessions.bulk-enroll');

        // Training Session Waitlist
        Route::get('/sessions/{session}/waitlist', [\App\Http\Controllers\Api\TrainingWaitlistController::class, 'index'])
            ->name('api.training.sessions.waitlist.index');
        Route::get('/waitlist/{waitlist}', [\App\Http\Controllers\Api\TrainingWaitlistController::class, 'show'])
            ->name('api.training.waitlist.show');
        Route::delete('/waitlist/{waitlist}', [\App\Http\Controllers\Api\TrainingWaitlistController::class, 'destroy'])
            ->name('api.training.waitlist.destroy');

        // Training Calendar
        Route::get('/calendar', [\App\Http\Controllers\Api\TrainingCalendarController::class, 'index'])
            ->name('api.training.calendar.index');

        // Course Sessions (for course detail page)
        Route::get('/courses/{course}/sessions', [\App\Http\Controllers\Api\TrainingSessionController::class, 'forCourse'])
            ->name('api.training.courses.sessions.index');

        // Training Enrollment Approval API
        Route::prefix('enrollment-approvals')->group(function () {
            Route::get('/pending', [\App\Http\Controllers\Api\TrainingEnrollmentApprovalController::class, 'pending'])
                ->name('api.training.enrollment-approvals.pending');
            Route::get('/summary', [\App\Http\Controllers\Api\TrainingEnrollmentApprovalController::class, 'summary'])
                ->name('api.training.enrollment-approvals.summary');
            Route::get('/history', [\App\Http\Controllers\Api\TrainingEnrollmentApprovalController::class, 'history'])
                ->name('api.training.enrollment-approvals.history');
            Route::post('/{enrollment}/approve', [\App\Http\Controllers\Api\TrainingEnrollmentApprovalController::class, 'approve'])
                ->name('api.training.enrollment-approvals.approve');
            Route::post('/{enrollment}/reject', [\App\Http\Controllers\Api\TrainingEnrollmentApprovalController::class, 'reject'])
                ->name('api.training.enrollment-approvals.reject');
        });
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

    /*
    |--------------------------------------------------------------------------
    | Compliance Training API Routes
    |--------------------------------------------------------------------------
    |
    | These endpoints manage compliance training courses, assignments, rules,
    | and employee progress tracking for mandatory safety and regulatory training.
    |
    */

    Route::prefix('compliance')->group(function () {
        // Dashboard Statistics
        Route::get('/dashboard', [\App\Http\Controllers\Api\ComplianceDashboardController::class, 'index'])
            ->name('api.compliance.dashboard');
        Route::get('/dashboard/trends', [\App\Http\Controllers\Api\ComplianceDashboardController::class, 'trends'])
            ->name('api.compliance.dashboard.trends');
        Route::get('/dashboard/employees-with-issues', [\App\Http\Controllers\Api\ComplianceDashboardController::class, 'employeesWithIssues'])
            ->name('api.compliance.dashboard.employees-with-issues');

        // Compliance Courses
        Route::get('/courses', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'index'])
            ->name('api.compliance.courses.index');
        Route::post('/courses', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'store'])
            ->name('api.compliance.courses.store');
        Route::get('/courses/{complianceCourse}', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'show'])
            ->name('api.compliance.courses.show');
        Route::put('/courses/{complianceCourse}', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'update'])
            ->name('api.compliance.courses.update');
        Route::delete('/courses/{complianceCourse}', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'destroy'])
            ->name('api.compliance.courses.destroy');
        Route::get('/courses/{complianceCourse}/statistics', [\App\Http\Controllers\Api\ComplianceCourseController::class, 'statistics'])
            ->name('api.compliance.courses.statistics');

        // Compliance Modules (nested under courses)
        Route::get('/courses/{complianceCourse}/modules', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'index'])
            ->name('api.compliance.modules.index');
        Route::post('/courses/{complianceCourse}/modules', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'store'])
            ->name('api.compliance.modules.store');
        Route::get('/courses/{complianceCourse}/modules/{complianceModule}', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'show'])
            ->name('api.compliance.modules.show');
        Route::put('/courses/{complianceCourse}/modules/{complianceModule}', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'update'])
            ->name('api.compliance.modules.update');
        Route::delete('/courses/{complianceCourse}/modules/{complianceModule}', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'destroy'])
            ->name('api.compliance.modules.destroy');
        Route::post('/courses/{complianceCourse}/modules/reorder', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'reorder'])
            ->name('api.compliance.modules.reorder');
        Route::get('/courses/{complianceCourse}/modules/{complianceModule}/download', [\App\Http\Controllers\Api\ComplianceModuleController::class, 'download'])
            ->name('api.compliance.modules.download');

        // Compliance Assignment Rules (nested under courses)
        Route::get('/rule-types', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'ruleTypes'])
            ->name('api.compliance.rule-types');
        Route::get('/courses/{complianceCourse}/rules', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'index'])
            ->name('api.compliance.rules.index');
        Route::post('/courses/{complianceCourse}/rules', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'store'])
            ->name('api.compliance.rules.store');
        Route::get('/courses/{complianceCourse}/rules/{rule}', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'show'])
            ->name('api.compliance.rules.show');
        Route::put('/courses/{complianceCourse}/rules/{rule}', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'update'])
            ->name('api.compliance.rules.update');
        Route::delete('/courses/{complianceCourse}/rules/{rule}', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'destroy'])
            ->name('api.compliance.rules.destroy');
        Route::post('/courses/{complianceCourse}/rules/preview', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'preview'])
            ->name('api.compliance.rules.preview');
        Route::post('/courses/{complianceCourse}/rules/{rule}/apply', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'apply'])
            ->name('api.compliance.rules.apply');
        Route::post('/courses/{complianceCourse}/rules/{rule}/toggle', [\App\Http\Controllers\Api\ComplianceRuleController::class, 'toggle'])
            ->name('api.compliance.rules.toggle');

        // Compliance Assignments
        Route::get('/assignments', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'index'])
            ->name('api.compliance.assignments.index');
        Route::post('/assignments', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'store'])
            ->name('api.compliance.assignments.store');
        Route::post('/assignments/bulk', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'bulkAssign'])
            ->name('api.compliance.assignments.bulk');
        Route::get('/assignments/{complianceAssignment}', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'show'])
            ->name('api.compliance.assignments.show');
        Route::delete('/assignments/{complianceAssignment}', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'destroy'])
            ->name('api.compliance.assignments.destroy');
        Route::post('/assignments/{complianceAssignment}/extend', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'extend'])
            ->name('api.compliance.assignments.extend');
        Route::post('/assignments/{complianceAssignment}/exempt', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'exempt'])
            ->name('api.compliance.assignments.exempt');
        Route::post('/assignments/{complianceAssignment}/revoke-exemption', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'revokeExemption'])
            ->name('api.compliance.assignments.revoke-exemption');
        Route::post('/assignments/{complianceAssignment}/reassign', [\App\Http\Controllers\Api\ComplianceAssignmentController::class, 'reassign'])
            ->name('api.compliance.assignments.reassign');
    });

    // My Compliance (Employee self-service)
    Route::prefix('my/compliance')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\MyComplianceController::class, 'index'])
            ->name('api.my.compliance.index');
        Route::get('/summary', [\App\Http\Controllers\Api\MyComplianceController::class, 'summary'])
            ->name('api.my.compliance.summary');
        Route::get('/certificates', [\App\Http\Controllers\Api\MyComplianceController::class, 'certificates'])
            ->name('api.my.compliance.certificates');
        Route::get('/{complianceAssignment}', [\App\Http\Controllers\Api\MyComplianceController::class, 'show'])
            ->name('api.my.compliance.show');
        Route::get('/{complianceAssignment}/next-module', [\App\Http\Controllers\Api\MyComplianceController::class, 'nextModule'])
            ->name('api.my.compliance.next-module');
        Route::post('/{complianceAssignment}/acknowledge', [\App\Http\Controllers\Api\MyComplianceController::class, 'acknowledge'])
            ->name('api.my.compliance.acknowledge');
        Route::get('/{complianceAssignment}/certificate/download', [\App\Http\Controllers\Api\MyComplianceController::class, 'downloadCertificate'])
            ->name('api.my.compliance.certificate.download');

        // Module Progress
        Route::post('/{complianceAssignment}/modules/{complianceModule}/start', [\App\Http\Controllers\Api\MyComplianceController::class, 'startModule'])
            ->name('api.my.compliance.modules.start');
        Route::post('/{complianceAssignment}/modules/{complianceModule}/progress', [\App\Http\Controllers\Api\MyComplianceController::class, 'updateProgress'])
            ->name('api.my.compliance.modules.progress');
        Route::post('/{complianceAssignment}/modules/{complianceModule}/complete', [\App\Http\Controllers\Api\MyComplianceController::class, 'completeModule'])
            ->name('api.my.compliance.modules.complete');

        // Assessment API
        Route::get('/{complianceAssignment}/modules/{complianceModule}/assessment/questions', [\App\Http\Controllers\Api\ComplianceAssessmentApiController::class, 'questions'])
            ->name('api.my.compliance.assessment.questions');
        Route::post('/{complianceAssignment}/modules/{complianceModule}/assessment/start', [\App\Http\Controllers\Api\ComplianceAssessmentApiController::class, 'start'])
            ->name('api.my.compliance.assessment.start');
        Route::post('/{complianceAssignment}/modules/{complianceModule}/assessment/{attempt}/submit', [\App\Http\Controllers\Api\ComplianceAssessmentApiController::class, 'submit'])
            ->name('api.my.compliance.assessment.submit');
        Route::get('/{complianceAssignment}/modules/{complianceModule}/assessment/attempts', [\App\Http\Controllers\Api\ComplianceAssessmentApiController::class, 'attempts'])
            ->name('api.my.compliance.assessment.attempts');
        Route::get('/{complianceAssignment}/modules/{complianceModule}/assessment/{attempt}/results', [\App\Http\Controllers\Api\ComplianceAssessmentApiController::class, 'attemptResults'])
            ->name('api.my.compliance.assessment.results');
    });

    // Team Compliance (Manager view)
    Route::prefix('team/compliance')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TeamComplianceController::class, 'index'])
            ->name('api.team.compliance.index');
        Route::get('/summary', [\App\Http\Controllers\Api\TeamComplianceController::class, 'summary'])
            ->name('api.team.compliance.summary');
        Route::get('/by-employee', [\App\Http\Controllers\Api\TeamComplianceController::class, 'byEmployee'])
            ->name('api.team.compliance.by-employee');
        Route::get('/overdue', [\App\Http\Controllers\Api\TeamComplianceController::class, 'overdue'])
            ->name('api.team.compliance.overdue');
        Route::get('/upcoming', [\App\Http\Controllers\Api\TeamComplianceController::class, 'upcoming'])
            ->name('api.team.compliance.upcoming');
    });
});
