<?php

use App\Http\Controllers\Api\AnnouncementController as ApiAnnouncementController;
use App\Http\Controllers\Api\BiometricSyncController;
use App\Http\Controllers\Api\CompanyDocumentController as ApiCompanyDocumentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\EmployeeCompensationController;
use App\Http\Controllers\Api\EmployeeController as ApiEmployeeController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Controllers\EmployeeAssignmentController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/unsync-from-device', [BiometricSyncController::class, 'unsyncEmployeeFromDevice'])
        ->name('api.employees.unsync-from-device');
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
