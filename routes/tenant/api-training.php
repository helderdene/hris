<?php

use Illuminate\Support\Facades\Route;

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
