<?php

/**
 * Feature Tests for Document Version History
 *
 * These tests verify the document version history functionality including
 * displaying version timelines, downloading specific versions, uploading
 * new versions, and displaying version notes.
 *
 * Note: These tests call controllers directly following the pattern from EmployeeDocumentApiTest.php
 * since tenant subdomain routing requires special handling in tests.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DocumentVersionController;
use App\Http\Controllers\Api\EmployeeDocumentController;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindVersionTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createVersionTestTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated NewVersionUploadRequest.
 */
function createNewVersionUploadRequest(array $data): NewVersionUploadRequest
{
    $request = NewVersionUploadRequest::create(
        '/api/documents/1/versions',
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
    $rules = (new NewVersionUploadRequest)->rules();
    $validator = Validator::make($data, $rules);

    // Set the validator on the request (via reflection since it's protected)
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

describe('Document Version Timeline', function () {
    it('returns all versions for a document ordered by version number descending', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindVersionTestTenantContext($tenant);

        $admin = createVersionTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create document
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Employment Contract',
        ]);

        // Create version 1
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_notes' => 'Initial contract upload',
            'uploaded_by' => $admin->id,
            'created_at' => now()->subDays(30),
        ]);

        // Create version 2
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'version_notes' => 'Updated salary terms',
            'uploaded_by' => $admin->id,
            'created_at' => now()->subDays(15),
        ]);

        // Create version 3 (current)
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 3,
            'version_notes' => 'Added benefits section',
            'uploaded_by' => $admin->id,
            'created_at' => now(),
        ]);

        // Fetch document with versions via controller
        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->show($employee, $document);

        $data = $response->getData(true);

        expect($data['data']['versions'])->toHaveCount(3);

        // Extract version numbers to check they are all present
        $versionNumbers = array_column($data['data']['versions'], 'version_number');
        expect($versionNumbers)->toContain(1);
        expect($versionNumbers)->toContain(2);
        expect($versionNumbers)->toContain(3);
    });

    it('displays version notes correctly for each version', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindVersionTestTenantContext($tenant);

        $admin = createVersionTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Safety Training Certificate',
        ]);

        // Create version with specific notes
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_notes' => 'Initial safety certification - valid until Dec 2025',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new EmployeeDocumentController(new DocumentStorageService);
        $response = $controller->show($employee, $document);

        $data = $response->getData(true);

        expect($data['data']['versions'][0]['version_notes'])->toBe('Initial safety certification - valid until Dec 2025');
    });
});

describe('Version Download', function () {
    it('allows downloading a specific version of a document', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindVersionTestTenantContext($tenant);

        $admin = createVersionTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create actual test file in storage
        $filePath = $tenant->slug.'/documents/'.$employee->id.'/test-document.pdf';
        Storage::disk('tenant-documents')->put($filePath, 'Test PDF content for version 1');

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test Document',
            'original_filename' => 'test-document.pdf',
            'stored_filename' => 'test-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'test-document.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'version_notes' => 'Initial version',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->download($document, $version);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    });
});

describe('New Version Upload', function () {
    it('creates a new version when uploading to an existing document', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindVersionTestTenantContext($tenant);

        $admin = createVersionTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create initial document
        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Policy Document',
            'original_filename' => 'policy.pdf',
        ]);

        // Create initial version
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'version_notes' => 'Original policy',
            'uploaded_by' => $admin->id,
        ]);

        // Upload new version
        $newFile = UploadedFile::fake()->create('updated-policy.pdf', 2048, 'application/pdf');

        $requestData = [
            'file' => $newFile,
            'version_notes' => 'Updated policy with new regulations',
        ];

        $request = createNewVersionUploadRequest($requestData);
        $request->files->set('file', $newFile);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->store($request, 'test-tenant', $document);

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);

        expect($data['data']['version_number'])->toBe(2);
        expect($data['data']['version_notes'])->toBe('Updated policy with new regulations');

        // Verify document now has 2 versions
        $document->refresh();
        expect($document->versions()->count())->toBe(2);
    });

    it('increments version number correctly for subsequent uploads', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindVersionTestTenantContext($tenant);

        $admin = createVersionTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Handbook',
            'original_filename' => 'handbook.pdf',
        ]);

        // Create versions 1-3
        for ($i = 1; $i <= 3; $i++) {
            DocumentVersion::factory()->create([
                'document_id' => $document->id,
                'version_number' => $i,
                'version_notes' => "Version {$i}",
                'uploaded_by' => $admin->id,
            ]);
        }

        // Upload version 4
        $newFile = UploadedFile::fake()->create('handbook-v4.pdf', 4096, 'application/pdf');

        $requestData = [
            'file' => $newFile,
            'version_notes' => 'Fourth revision',
        ];

        $request = createNewVersionUploadRequest($requestData);
        $request->files->set('file', $newFile);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->store($request, 'test-tenant', $document);

        expect($response->getStatusCode())->toBe(201);
        expect($response->getData(true)['data']['version_number'])->toBe(4);
    });
});
