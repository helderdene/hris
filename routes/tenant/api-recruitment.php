<?php

use Illuminate\Support\Facades\Route;

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
