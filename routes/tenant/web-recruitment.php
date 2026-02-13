<?php

use App\Http\Controllers\Hr\CertificationPageController;
use Illuminate\Support\Facades\Route;

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
