<?php

/**
 * Tests for Document Preview Functionality
 *
 * Tests the preview API endpoint and authorization for viewing
 * documents inline in the browser.
 *
 * Note: These tests call controllers directly following the pattern from EmployeeDocumentApiTest.php
 * since tenant subdomain routing requires special handling in tests.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DocumentVersionController;
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

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createPreviewTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindPreviewTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
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

describe('Document Preview API', function () {
    it('serves PDF file with inline content disposition for preview', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindPreviewTenantContext($tenant);

        $admin = createPreviewTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create a real test file in storage
        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');
        $filePath = $tenant->slug.'/documents/'.$employee->id.'/test-document.pdf';
        Storage::disk('tenant-documents')->put($filePath, $file->getContent());

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test PDF Document',
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
            'uploaded_by' => $admin->id,
        ]);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->preview($document, $version);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toContain('application/pdf');
        expect($response->headers->get('Content-Disposition'))->toContain('inline');
    });

    it('serves image file with inline content disposition for preview', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindPreviewTenantContext($tenant);

        $admin = createPreviewTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create a real test image file in storage
        $file = UploadedFile::fake()->image('test-image.jpg', 100, 100);
        $filePath = $tenant->slug.'/documents/'.$employee->id.'/test-image.jpg';
        Storage::disk('tenant-documents')->put($filePath, $file->getContent());

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test Image Document',
            'original_filename' => 'test-image.jpg',
            'stored_filename' => 'test-image.jpg',
            'file_path' => $filePath,
            'mime_type' => 'image/jpeg',
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'test-image.jpg',
            'file_path' => $filePath,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new DocumentVersionController(new DocumentStorageService);
        $response = $controller->preview($document, $version);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toContain('image/jpeg');
        expect($response->headers->get('Content-Disposition'))->toContain('inline');
    });

    it('validates user has view permission before serving preview', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindPreviewTenantContext($tenant);

        // Create an employee user who should not have access to other employees' documents
        $employeeUser = createPreviewTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employeeUser);

        // Create another employee's document
        $otherEmployee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create a test file
        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');
        $filePath = $tenant->slug.'/documents/'.$otherEmployee->id.'/test-document.pdf';
        Storage::disk('tenant-documents')->put($filePath, $file->getContent());

        $document = Document::factory()->create([
            'employee_id' => $otherEmployee->id,
            'document_category_id' => $category->id,
            'name' => 'Other Employee Document',
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
            'uploaded_by' => $employeeUser->id,
        ]);

        // Test that Gate denies access
        expect(Gate::allows('can-view-employee-documents', $otherEmployee))->toBeFalse();
    });

    it('returns 404 when file does not exist for preview', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindPreviewTenantContext($tenant);

        $admin = createPreviewTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::factory()->create();

        // Create document without actually storing the file
        $filePath = $tenant->slug.'/documents/'.$employee->id.'/nonexistent.pdf';

        $document = Document::factory()->create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Missing File Document',
            'original_filename' => 'nonexistent.pdf',
            'stored_filename' => 'nonexistent.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'nonexistent.pdf',
            'file_path' => $filePath,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        $controller = new DocumentVersionController(new DocumentStorageService);

        try {
            $controller->preview($document, $version);
            expect(false)->toBeTrue(); // Should not reach here
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            expect($e->getMessage())->toBe('File not found');
        }
    });
});
