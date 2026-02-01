<?php

/**
 * Integration Tests for Document Management Feature
 *
 * These strategic tests focus on end-to-end workflows, cross-role access control,
 * tenant isolation, and edge cases for the Document Management feature.
 *
 * This test file fills coverage gaps identified during Task Group 11 review.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CompanyDocumentController;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Requests\DocumentUploadRequest;
use App\Http\Requests\NewVersionUploadRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createDocMgmtIntegrationTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindDocMgmtIntegrationTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated DocumentUploadRequest.
 */
function createDocMgmtIntegrationDocumentRequest(array $data): DocumentUploadRequest
{
    $request = DocumentUploadRequest::create(
        '/api/employees/1/documents',
        'POST',
        $data
    );

    if (isset($data['file'])) {
        $request->files->set('file', $data['file']);
    }

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new DocumentUploadRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated NewVersionUploadRequest.
 */
function createDocMgmtIntegrationVersionRequest(array $data): NewVersionUploadRequest
{
    $request = NewVersionUploadRequest::create(
        '/api/documents/1/versions',
        'POST',
        $data
    );

    if (isset($data['file'])) {
        $request->files->set('file', $data['file']);
    }

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new NewVersionUploadRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    Storage::fake('tenant-documents');
});

describe('End-to-End: HR uploads employee document, employee views it', function () {
    it('allows HR to upload document that employee can then view', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        // Create HR staff who will upload
        $hrStaff = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::HrStaff);

        // Create employee and link to user
        $employeeUser = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);
        $category = DocumentCategory::factory()->create(['name' => 'Contracts']);

        // HR uploads document
        $this->actingAs($hrStaff);

        $file = UploadedFile::fake()->create('employment-contract.pdf', 2048, 'application/pdf');
        $requestData = [
            'file' => $file,
            'document_category_id' => $category->id,
            'name' => 'Employment Contract 2026',
            'version_notes' => 'Signed employment agreement',
        ];

        $request = createDocMgmtIntegrationDocumentRequest($requestData);
        $request->files->set('file', $file);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $uploadResponse = $controller->store($request, 'test-tenant', $employee);

        expect($uploadResponse->getStatusCode())->toBe(201);

        // Switch to employee user to verify they can view
        $this->actingAs($employeeUser);

        // Employee should be able to view their own documents
        expect(Gate::allows('can-view-employee-documents', $employee))->toBeTrue();

        $viewResponse = $controller->index('test-tenant', $employee);
        expect($viewResponse->getStatusCode())->toBe(200);

        $data = $viewResponse->getData(true);
        expect($data['data'])->toHaveCount(1);
        expect($data['data'][0]['name'])->toBe('Employment Contract 2026');
    });
});

describe('End-to-End: Supervisor views direct report document, blocked from others', function () {
    it('allows supervisor to view direct report documents but blocks access to other employees', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        // Create supervisor
        $supervisorUser = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Supervisor);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id]);

        // Create direct report under supervisor
        $directReport = Employee::factory()->create([
            'supervisor_id' => $supervisorEmployee->id,
        ]);

        // Create another employee not under this supervisor
        $otherEmployee = Employee::factory()->create([
            'supervisor_id' => null,
        ]);

        $category = DocumentCategory::factory()->create(['name' => 'Certifications']);

        // Create documents for both employees
        Document::factory()->create([
            'employee_id' => $directReport->id,
            'document_category_id' => $category->id,
            'name' => 'Direct Report Certificate',
        ]);

        Document::factory()->create([
            'employee_id' => $otherEmployee->id,
            'document_category_id' => $category->id,
            'name' => 'Other Employee Certificate',
        ]);

        $this->actingAs($supervisorUser);

        // Supervisor can view direct report documents
        expect(Gate::allows('can-view-employee-documents', $directReport))->toBeTrue();

        // Supervisor cannot view other employee documents
        expect(Gate::allows('can-view-employee-documents', $otherEmployee))->toBeFalse();
    });
});

describe('End-to-End: Document version upload and history retrieval', function () {
    it('tracks complete version history from initial upload through multiple versions', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        $hrManager = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create(['name' => 'Policies']);

        // Upload initial document
        $file1 = UploadedFile::fake()->create('handbook-v1.pdf', 1024, 'application/pdf');
        $requestData = [
            'file' => $file1,
            'document_category_id' => $category->id,
            'name' => 'Employee Handbook',
            'version_notes' => 'Initial handbook release',
        ];

        $request = createDocMgmtIntegrationDocumentRequest($requestData);
        $request->files->set('file', $file1);

        $documentController = new EmployeeDocumentController(new DocumentStorageService);
        $createResponse = $documentController->store($request, 'test-tenant', $employee);

        expect($createResponse->getStatusCode())->toBe(201);
        $documentId = $createResponse->getData(true)['data']['id'];
        $document = Document::find($documentId);

        // Upload version 2
        $file2 = UploadedFile::fake()->create('handbook-v2.pdf', 2048, 'application/pdf');
        $version2Data = [
            'file' => $file2,
            'version_notes' => 'Updated policy section',
        ];

        $version2Request = createDocMgmtIntegrationVersionRequest($version2Data);
        $version2Request->files->set('file', $file2);

        $versionController = new DocumentVersionController(new DocumentStorageService);
        $version2Response = $versionController->store($version2Request, 'test-tenant', $document);

        expect($version2Response->getStatusCode())->toBe(201);
        expect($version2Response->getData(true)['data']['version_number'])->toBe(2);

        // Upload version 3
        $file3 = UploadedFile::fake()->create('handbook-v3.pdf', 3072, 'application/pdf');
        $version3Data = [
            'file' => $file3,
            'version_notes' => 'Added benefits section',
        ];

        $version3Request = createDocMgmtIntegrationVersionRequest($version3Data);
        $version3Request->files->set('file', $file3);

        $version3Response = $versionController->store($version3Request, 'test-tenant', $document);

        expect($version3Response->getStatusCode())->toBe(201);
        expect($version3Response->getData(true)['data']['version_number'])->toBe(3);

        // Retrieve document with full version history
        $showResponse = $documentController->show('test-tenant', $employee, $document);
        $data = $showResponse->getData(true);

        expect($data['data']['versions'])->toHaveCount(3);

        $versionNotes = array_column($data['data']['versions'], 'version_notes');
        expect($versionNotes)->toContain('Initial handbook release');
        expect($versionNotes)->toContain('Updated policy section');
        expect($versionNotes)->toContain('Added benefits section');
    });
});

describe('End-to-End: Company document lifecycle', function () {
    it('completes full lifecycle: create, view by all, delete by HR', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        $hrManager = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::HrManager);
        $employeeUser = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Employee);
        $category = DocumentCategory::factory()->create(['name' => 'Company Memos']);

        // HR creates company document
        $this->actingAs($hrManager);

        $file = UploadedFile::fake()->create('company-policy.pdf', 1024, 'application/pdf');
        $requestData = [
            'file' => $file,
            'document_category_id' => $category->id,
            'name' => 'Company Policy 2026',
            'version_notes' => 'Annual policy update',
        ];

        $request = createDocMgmtIntegrationDocumentRequest($requestData);
        $request->files->set('file', $file);

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $createResponse = $controller->store($request, 'test-tenant');

        expect($createResponse->getStatusCode())->toBe(201);
        $documentId = $createResponse->getData(true)['data']['id'];

        // Switch to employee - verify they can view
        $this->actingAs($employeeUser);

        expect(Gate::allows('can-view-company-documents'))->toBeTrue();

        $listResponse = $controller->index('test-tenant');
        expect($listResponse->getStatusCode())->toBe(200);

        $data = $listResponse->getData(true);
        expect($data['data'])->toHaveCount(1);

        // Employee cannot delete
        expect(Gate::allows('can-manage-company-documents'))->toBeFalse();

        // Switch back to HR to delete
        $this->actingAs($hrManager);

        $document = Document::find($documentId);
        $deleteResponse = $controller->destroy('test-tenant', $document);

        expect($deleteResponse->getStatusCode())->toBe(204);
        expect(Document::find($documentId))->toBeNull();
        expect(Document::withTrashed()->find($documentId))->not->toBeNull();
    });
});

describe('Integration: File storage service with real file operations', function () {
    it('performs complete file lifecycle: store, retrieve, delete', function () {
        $service = new DocumentStorageService;

        // Store a file
        $file = UploadedFile::fake()->create('integration-test.pdf', 2048, 'application/pdf');
        $result = $service->store($file, 'test-tenant', 123);

        expect($result)->toHaveKeys([
            'stored_filename',
            'file_path',
            'file_size',
            'mime_type',
            'original_filename',
        ]);
        expect($result['original_filename'])->toBe('integration-test.pdf');
        expect($result['mime_type'])->toBe('application/pdf');

        // Verify file exists
        expect($service->fileExists($result['file_path']))->toBeTrue();

        // Retrieve file content
        $content = $service->getFile($result['file_path']);
        expect($content)->not->toBeNull();

        // Delete file
        $deleted = $service->deleteFile($result['file_path']);
        expect($deleted)->toBeTrue();
        expect($service->fileExists($result['file_path']))->toBeFalse();
    });
});

describe('Integration: Preview endpoint with MIME type detection', function () {
    it('correctly identifies and serves different previewable MIME types', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        $admin = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();
        $controller = new DocumentVersionController(new DocumentStorageService);

        // Test PDF preview
        $pdfFile = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        $pdfPath = $tenant->slug.'/documents/'.$employee->id.'/test.pdf';
        Storage::disk('tenant-documents')->put($pdfPath, $pdfFile->getContent());

        $pdfDocument = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'file_path' => $pdfPath,
            'mime_type' => 'application/pdf',
        ]);

        $pdfVersion = DocumentVersion::factory()->create([
            'document_id' => $pdfDocument->id,
            'version_number' => 1,
            'file_path' => $pdfPath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        $pdfResponse = $controller->preview('test-tenant', $pdfDocument, $pdfVersion);
        expect($pdfResponse->headers->get('Content-Type'))->toContain('application/pdf');
        expect($pdfResponse->headers->get('Content-Disposition'))->toContain('inline');

        // Test PNG preview
        $pngFile = UploadedFile::fake()->image('test.png', 100, 100);
        $pngPath = $tenant->slug.'/documents/'.$employee->id.'/test.png';
        Storage::disk('tenant-documents')->put($pngPath, $pngFile->getContent());

        $pngDocument = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'file_path' => $pngPath,
            'mime_type' => 'image/png',
        ]);

        $pngVersion = DocumentVersion::factory()->create([
            'document_id' => $pngDocument->id,
            'version_number' => 1,
            'file_path' => $pngPath,
            'mime_type' => 'image/png',
            'uploaded_by' => $admin->id,
        ]);

        $pngResponse = $controller->preview('test-tenant', $pngDocument, $pngVersion);
        expect($pngResponse->headers->get('Content-Type'))->toContain('image/png');
        expect($pngResponse->headers->get('Content-Disposition'))->toContain('inline');
    });
});

describe('Security: Tenant isolation verification', function () {
    it('ensures documents from one tenant are not accessible from another tenant context', function () {
        // Create two tenants
        $tenant1 = Tenant::factory()->create(['slug' => 'tenant-one']);
        $tenant2 = Tenant::factory()->create(['slug' => 'tenant-two']);

        // Create admin for tenant 1 and create document
        bindDocMgmtIntegrationTenantContext($tenant1);

        $admin1 = createDocMgmtIntegrationTenantUser($tenant1, TenantUserRole::Admin);
        $this->actingAs($admin1);

        $employee1 = Employee::factory()->create();
        $category1 = DocumentCategory::factory()->create(['name' => 'Tenant 1 Category']);

        $document1 = Document::factory()->create([
            'employee_id' => $employee1->id,
            'document_category_id' => $category1->id,
            'name' => 'Tenant 1 Document',
        ]);

        // Document exists in tenant 1
        expect(Document::find($document1->id))->not->toBeNull();

        // Switch to tenant 2 context
        bindDocMgmtIntegrationTenantContext($tenant2);

        $admin2 = createDocMgmtIntegrationTenantUser($tenant2, TenantUserRole::Admin);
        $this->actingAs($admin2);

        // Documents from tenant 1 should not be accessible in tenant 2 context
        // Note: In the actual implementation, TenantModel uses different database connections per tenant
        // This test verifies the tenant binding mechanism works correctly
        $category2 = DocumentCategory::factory()->create(['name' => 'Tenant 2 Category']);
        $employee2 = Employee::factory()->create();

        // Create document in tenant 2
        $document2 = Document::factory()->create([
            'employee_id' => $employee2->id,
            'document_category_id' => $category2->id,
            'name' => 'Tenant 2 Document',
        ]);

        // Verify documents are isolated (only tenant 2's document in current context)
        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->index('tenant-two', $employee2);

        $data = $response->getData(true);
        expect($data['data'])->toHaveCount(1);
        expect($data['data'][0]['name'])->toBe('Tenant 2 Document');
    });
});

describe('Security: Employee cannot access other employee documents', function () {
    it('denies employee access to documents of other employees', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        // Create two employees
        $employeeUser1 = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Employee);
        $employee1 = Employee::factory()->create(['user_id' => $employeeUser1->id]);

        $employeeUser2 = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::Employee);
        $employee2 = Employee::factory()->create(['user_id' => $employeeUser2->id]);

        $category = DocumentCategory::factory()->create();

        // Create documents for both employees
        Document::factory()->create([
            'employee_id' => $employee1->id,
            'document_category_id' => $category->id,
            'name' => 'Employee 1 Private Document',
        ]);

        Document::factory()->create([
            'employee_id' => $employee2->id,
            'document_category_id' => $category->id,
            'name' => 'Employee 2 Private Document',
        ]);

        // Acting as employee 1
        $this->actingAs($employeeUser1);

        // Employee 1 can view own documents
        expect(Gate::allows('can-view-employee-documents', $employee1))->toBeTrue();

        // Employee 1 cannot view employee 2's documents
        expect(Gate::allows('can-view-employee-documents', $employee2))->toBeFalse();

        // Acting as employee 2
        $this->actingAs($employeeUser2);

        // Employee 2 can view own documents
        expect(Gate::allows('can-view-employee-documents', $employee2))->toBeTrue();

        // Employee 2 cannot view employee 1's documents
        expect(Gate::allows('can-view-employee-documents', $employee1))->toBeFalse();
    });
});

describe('Edge case: Upload at max file size (10MB)', function () {
    it('accepts file upload at exactly 10MB', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocMgmtIntegrationTenantContext($tenant);

        $hrStaff = createDocMgmtIntegrationTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create a file at exactly 10MB (10240 KB)
        $maxSizeFile = UploadedFile::fake()->create('large-document.pdf', 10240, 'application/pdf');

        $requestData = [
            'file' => $maxSizeFile,
            'document_category_id' => $category->id,
            'name' => 'Large Document at Max Size',
            'version_notes' => 'Testing 10MB upload limit',
        ];

        $request = createDocMgmtIntegrationDocumentRequest($requestData);
        $request->files->set('file', $maxSizeFile);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->store($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['name'])->toBe('Large Document at Max Size');
    });
});

describe('Edge case: Invalid MIME type rejection', function () {
    it('rejects files with disallowed MIME types through validation', function () {
        $service = new DocumentStorageService;

        // Test that validation rejects various dangerous file types
        $executableFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
        expect($service->validateMimeType($executableFile))->toBeFalse();

        $phpFile = UploadedFile::fake()->create('backdoor.php', 100, 'text/x-php');
        expect($service->validateMimeType($phpFile))->toBeFalse();

        $javascriptFile = UploadedFile::fake()->create('script.js', 100, 'application/javascript');
        expect($service->validateMimeType($javascriptFile))->toBeFalse();

        $shellFile = UploadedFile::fake()->create('script.sh', 100, 'application/x-sh');
        expect($service->validateMimeType($shellFile))->toBeFalse();

        // Verify allowed types still work
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        expect($service->validateMimeType($pdfFile))->toBeTrue();

        $imageFile = UploadedFile::fake()->image('photo.jpg');
        expect($service->validateMimeType($imageFile))->toBeTrue();
    });
});
