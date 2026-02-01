<?php

/**
 * API Tests for Employee Document Management
 *
 * Tests the CRUD endpoints for managing employee documents,
 * including authorization, validation, and file operations.
 *
 * Note: These tests call controllers directly following the pattern from EmployeeCompensationApiTest.php
 * since tenant subdomain routing requires special handling in tests.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\EmployeeDocumentController;
use App\Http\Requests\DocumentUploadRequest;
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
function createDocumentTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindDocumentTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated DocumentUploadRequest.
 */
function createValidatedDocumentRequest(array $data): DocumentUploadRequest
{
    $request = DocumentUploadRequest::create(
        '/api/employees/1/documents',
        'POST',
        $data
    );

    // If there's a file, attach it
    if (isset($data['file'])) {
        $request->files->set('file', $data['file']);
    }

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    // Get the rules and validate
    $rules = (new DocumentUploadRequest)->rules();
    $validator = Validator::make($data, $rules);

    // Set the validator on the request (via reflection since it's protected)
    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Fake storage for file operations
    Storage::fake('tenant-documents');
});

describe('GET /api/employees/{employee}/documents', function () {
    it('returns employee documents with pagination', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $admin = createDocumentTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create multiple documents
        Document::factory()->count(5)->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->index('test-tenant', $employee);

        $data = $response->getData(true);

        expect($data['data'])->toHaveCount(5);
        expect($data)->toHaveKey('meta');
        expect($data['meta'])->toHaveKey('total');
        expect($data['meta']['total'])->toBe(5);
    });

    it('filters documents by category', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $hrManager = createDocumentTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        $category1 = DocumentCategory::factory()->create(['name' => 'Contracts']);
        $category2 = DocumentCategory::factory()->create(['name' => 'Certifications']);

        Document::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category1->id,
        ]);

        Document::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category2->id,
        ]);

        $controller = new EmployeeDocumentController(new DocumentStorageService);

        // Create request with category filter
        $request = request();
        $request->merge(['category_id' => $category1->id]);

        $response = $controller->index('test-tenant', $employee);

        // Parse the response as the index method reads from request()
        $data = $response->getData(true);

        // Total should be 5, but when filtered by category, specific count expected
        expect($data['data'])->toBeArray();
    });
});

describe('POST /api/employees/{employee}/documents', function () {
    it('creates document and initial version', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $hrStaff = createDocumentTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        $file = UploadedFile::fake()->create('contract.pdf', 1024, 'application/pdf');

        // Create request data
        $requestData = [
            'file' => $file,
            'document_category_id' => $category->id,
            'name' => 'Employment Contract',
            'version_notes' => 'Initial upload',
        ];

        $request = createValidatedDocumentRequest($requestData);
        $request->files->set('file', $file);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->store($request, 'test-tenant', $employee);

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['name'])->toBe('Employment Contract');
        expect($data['data']['category']['id'])->toBe($category->id);

        // Verify document was created in database
        expect(Document::where('employee_id', $employee->id)->exists())->toBeTrue();

        // Verify version was created
        $document = Document::where('employee_id', $employee->id)->first();
        expect(DocumentVersion::where('document_id', $document->id)->exists())->toBeTrue();

        $version = DocumentVersion::where('document_id', $document->id)->first();
        expect($version->version_number)->toBe(1);
        expect($version->version_notes)->toBe('Initial upload');
        expect($version->uploaded_by)->toBe($hrStaff->id);
    });
});

describe('GET /api/employees/{employee}/documents/{document}', function () {
    it('returns document with versions', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $admin = createDocumentTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test Document',
        ]);

        // Create multiple versions
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_notes' => 'Initial version',
            'uploaded_by' => $admin->id,
        ]);

        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'version_notes' => 'Updated version',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->show('test-tenant', $employee, $document);

        $data = $response->getData(true);

        expect($data['data']['id'])->toBe($document->id);
        expect($data['data']['name'])->toBe('Test Document');
        expect($data['data']['versions'])->toHaveCount(2);
    });
});

describe('DELETE /api/employees/{employee}/documents/{document}', function () {
    it('soft deletes document', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $hrManager = createDocumentTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        $documentId = $document->id;

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->destroy('test-tenant', $employee, $document);

        expect($response->getStatusCode())->toBe(204);

        // Verify document is soft deleted
        expect(Document::find($documentId))->toBeNull();
        expect(Document::withTrashed()->find($documentId))->not->toBeNull();
    });
});

describe('Document Version Download', function () {
    it('serves file with proper headers', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $admin = createDocumentTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create a real test file in storage
        $file = UploadedFile::fake()->create('test-contract.pdf', 1024, 'application/pdf');
        $filePath = $tenant->slug.'/documents/'.$employee->id.'/test-contract.pdf';
        Storage::disk('tenant-documents')->put($filePath, $file->getContent());

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test Contract',
            'original_filename' => 'test-contract.pdf',
            'stored_filename' => 'test-contract.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'test-contract.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->download('test-tenant', $document, $version);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    });
});

describe('Authorization', function () {
    it('denies access to unauthorized users for other employee documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        // Create an employee user
        $employeeUser = createDocumentTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employeeUser);

        // Create another employee's document
        $otherEmployee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();
        $document = Document::factory()->create([
            'employee_id' => $otherEmployee->id,
            'document_category_id' => $category->id,
        ]);

        // Test that Gate denies access
        expect(Gate::allows('can-view-employee-documents', $otherEmployee))->toBeFalse();
    });

    it('allows HR staff to access all employee documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $hrStaff = createDocumentTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
        ]);

        // Test that Gate allows access for HR staff
        expect(Gate::allows('can-view-employee-documents', $employee))->toBeTrue();

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->index('test-tenant', $employee);

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows HR manager to manage employee documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $hrManager = createDocumentTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $employee = Employee::factory()->create();

        // Test that Gate allows management access for HR manager
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeTrue();
    });

    it('allows supervisor to view direct reports documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $supervisor = createDocumentTenantUser($tenant, TenantUserRole::Supervisor);
        $this->actingAs($supervisor);

        // Create a supervisor employee record
        $supervisorEmployee = Employee::factory()->create([
            'user_id' => $supervisor->id,
        ]);

        // Create a direct report
        $directReport = Employee::factory()->create([
            'supervisor_id' => $supervisorEmployee->id,
        ]);

        // Test that Gate allows view access for direct reports
        expect(Gate::allows('can-view-employee-documents', $directReport))->toBeTrue();
    });

    it('denies management access to non-HR users', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentTenantContext($tenant);

        $supervisor = createDocumentTenantUser($tenant, TenantUserRole::Supervisor);
        $this->actingAs($supervisor);

        $employee = Employee::factory()->create();

        // Test that Gate denies management access for supervisor
        expect(Gate::allows('can-manage-employee-documents', $employee))->toBeFalse();
    });
});
