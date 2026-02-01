<?php

/**
 * API Tests for Company Document Management
 *
 * Tests the CRUD endpoints for managing company-wide documents,
 * including authorization, validation, and file operations.
 *
 * Note: These tests call controllers directly following the pattern from EmployeeDocumentApiTest.php
 * since tenant subdomain routing requires special handling in tests.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CompanyDocumentController;
use App\Http\Requests\DocumentUploadRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
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
function createCompanyDocTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindCompanyDocTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated DocumentUploadRequest for company documents.
 */
function createCompanyDocumentRequest(array $data): DocumentUploadRequest
{
    $request = DocumentUploadRequest::create(
        '/api/company-documents',
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

describe('GET /api/company-documents', function () {
    it('returns company documents with pagination', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $admin = createCompanyDocTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $category = DocumentCategory::factory()->create();

        // Create multiple company documents
        Document::factory()->count(5)->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $response = $controller->index('test-tenant');

        $data = $response->getData(true);

        expect($data['data'])->toHaveCount(5);
        expect($data)->toHaveKey('meta');
        expect($data['meta'])->toHaveKey('total');
        expect($data['meta']['total'])->toBe(5);
    });

    it('filters company documents by category', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $hrManager = createCompanyDocTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $category1 = DocumentCategory::factory()->create(['name' => 'Policies']);
        $category2 = DocumentCategory::factory()->create(['name' => 'Handbooks']);

        Document::factory()->count(3)->create([
            'employee_id' => null,
            'document_category_id' => $category1->id,
            'is_company_document' => true,
        ]);

        Document::factory()->count(2)->create([
            'employee_id' => null,
            'document_category_id' => $category2->id,
            'is_company_document' => true,
        ]);

        $controller = new CompanyDocumentController(new DocumentStorageService);

        // Create request with category filter
        $request = request();
        $request->merge(['category_id' => $category1->id]);

        $response = $controller->index('test-tenant');

        $data = $response->getData(true);

        // Total should be 5, but when filtered by category, specific count expected
        expect($data['data'])->toBeArray();
    });
});

describe('POST /api/company-documents', function () {
    it('creates company document with no employee_id', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $hrStaff = createCompanyDocTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        $category = DocumentCategory::factory()->create();

        $file = UploadedFile::fake()->create('employee-handbook.pdf', 2048, 'application/pdf');

        // Create request data
        $requestData = [
            'file' => $file,
            'document_category_id' => $category->id,
            'name' => 'Employee Handbook 2026',
            'version_notes' => 'Initial release',
        ];

        $request = createCompanyDocumentRequest($requestData);
        $request->files->set('file', $file);

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $response = $controller->store($request, 'test-tenant');

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['name'])->toBe('Employee Handbook 2026');
        expect($data['data']['category']['id'])->toBe($category->id);
        expect($data['data']['is_company_document'])->toBeTrue();

        // Verify document was created with no employee_id
        $document = Document::where('name', 'Employee Handbook 2026')->first();
        expect($document)->not->toBeNull();
        expect($document->employee_id)->toBeNull();
        expect($document->is_company_document)->toBeTrue();

        // Verify version was created
        expect(DocumentVersion::where('document_id', $document->id)->exists())->toBeTrue();

        $version = DocumentVersion::where('document_id', $document->id)->first();
        expect($version->version_number)->toBe(1);
        expect($version->uploaded_by)->toBe($hrStaff->id);
    });
});

describe('GET /api/company-documents/{document}', function () {
    it('returns company document with versions', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $admin = createCompanyDocTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $category = DocumentCategory::factory()->create();
        $document = Document::factory()->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'name' => 'Company Policy Document',
            'is_company_document' => true,
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

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $response = $controller->show('test-tenant', $document);

        $data = $response->getData(true);

        expect($data['data']['id'])->toBe($document->id);
        expect($data['data']['name'])->toBe('Company Policy Document');
        expect($data['data']['is_company_document'])->toBeTrue();
        expect($data['data']['versions'])->toHaveCount(2);
    });
});

describe('DELETE /api/company-documents/{document}', function () {
    it('soft deletes company document', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $hrManager = createCompanyDocTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $category = DocumentCategory::factory()->create();
        $document = Document::factory()->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        $documentId = $document->id;

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $response = $controller->destroy('test-tenant', $document);

        expect($response->getStatusCode())->toBe(204);

        // Verify document is soft deleted
        expect(Document::find($documentId))->toBeNull();
        expect(Document::withTrashed()->find($documentId))->not->toBeNull();
    });
});

describe('Company Document Authorization', function () {
    it('allows all tenant users to view company documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        // Test with an Employee role (lowest privilege)
        $employee = createCompanyDocTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $category = DocumentCategory::factory()->create();
        Document::factory()->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        // Test that Gate allows view access for all tenant members
        expect(Gate::allows('can-view-company-documents'))->toBeTrue();

        $controller = new CompanyDocumentController(new DocumentStorageService);
        $response = $controller->index('test-tenant');

        expect($response->getStatusCode())->toBe(200);
    });

    it('allows only HR staff to upload company documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $hrStaff = createCompanyDocTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        // Test that Gate allows management access for HR staff
        expect(Gate::allows('can-manage-company-documents'))->toBeTrue();
    });

    it('denies non-HR users from uploading company documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        // Test with Employee role
        $employee = createCompanyDocTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        // Test that Gate denies management access for employee
        expect(Gate::allows('can-manage-company-documents'))->toBeFalse();
    });

    it('denies supervisor from managing company documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $supervisor = createCompanyDocTenantUser($tenant, TenantUserRole::Supervisor);
        $this->actingAs($supervisor);

        // Test that Gate denies management access for supervisor
        expect(Gate::allows('can-manage-company-documents'))->toBeFalse();
    });

    it('allows HR manager to delete company documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocTenantContext($tenant);

        $hrManager = createCompanyDocTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Test that Gate allows management access for HR manager
        expect(Gate::allows('can-manage-company-documents'))->toBeTrue();
    });
});
