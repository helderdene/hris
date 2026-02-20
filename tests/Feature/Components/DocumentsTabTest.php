<?php

/**
 * Feature Tests for Documents Tab Vue Component
 *
 * These tests verify that the Employee Show page correctly supports
 * the Documents tab functionality including document listing, filtering,
 * uploading, and permission-based access.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindDocumentsTestTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createDocumentsTestTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Seed predefined categories
    Artisan::call('db:seed', [
        '--class' => 'DocumentCategorySeeder',
    ]);
});

describe('Employee Show Page with Documents Tab', function () {
    it('renders employee show page with documents tab available', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Employee',
        ]);

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);

        $componentProperty = $reflection->getProperty('component');
        $componentProperty->setAccessible(true);
        expect($componentProperty->getValue($inertiaResponse))->toBe('Employees/Show');

        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        expect($props['employee'])->not->toBeNull();
        $employeeData = $props['employee']->toArray(request());
        expect($employeeData['id'])->toBe($employee->id);
    });

    it('provides employee id needed for documents tab to fetch data', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();

        $controller = new EmployeeController;
        $inertiaResponse = $controller->show($employee);

        $reflection = new ReflectionClass($inertiaResponse);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($inertiaResponse);

        $employeeData = $props['employee']->toArray(request());
        expect($employeeData['id'])->toBeInt();
        expect($employeeData['id'])->toBeGreaterThan(0);
    });
});

describe('Documents Tab Data Display', function () {
    it('returns documents list when employee has documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::where('is_predefined', true)->first();

        $document = Document::create([
            'employee_id' => $employee->id,
            'document_category_id' => $category->id,
            'name' => 'Test Contract',
            'original_filename' => 'test-contract.pdf',
            'stored_filename' => 'unique-test-contract.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-test-contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_company_document' => false,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'stored_filename' => 'unique-test-contract.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-test-contract.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version_notes' => 'Initial upload',
            'uploaded_by' => $admin->id,
        ]);

        $loadedEmployee = Employee::with('documents.category', 'documents.versions')->find($employee->id);

        expect($loadedEmployee->documents)->toHaveCount(1);
        expect($loadedEmployee->documents->first()->name)->toBe('Test Contract');
        expect($loadedEmployee->documents->first()->category->name)->not->toBeNull();
    });

    it('returns empty documents list when employee has no documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $loadedEmployee = Employee::with('documents')->find($employee->id);

        expect($loadedEmployee->documents)->toBeEmpty();
    });

    it('returns documents filtered by category', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $contractsCategory = DocumentCategory::where('name', 'Contracts')->first();
        $certificationsCategory = DocumentCategory::where('name', 'Certifications')->first();

        // Create contract document
        $contractDoc = Document::create([
            'employee_id' => $employee->id,
            'document_category_id' => $contractsCategory->id,
            'name' => 'Employment Contract',
            'original_filename' => 'contract.pdf',
            'stored_filename' => 'unique-contract.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_company_document' => false,
        ]);

        DocumentVersion::create([
            'document_id' => $contractDoc->id,
            'version_number' => 1,
            'stored_filename' => 'unique-contract.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-contract.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        // Create certification document
        $certDoc = Document::create([
            'employee_id' => $employee->id,
            'document_category_id' => $certificationsCategory->id,
            'name' => 'Safety Training Certificate',
            'original_filename' => 'certificate.pdf',
            'stored_filename' => 'unique-certificate.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-certificate.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'is_company_document' => false,
        ]);

        DocumentVersion::create([
            'document_id' => $certDoc->id,
            'version_number' => 1,
            'stored_filename' => 'unique-certificate.pdf',
            'file_path' => $tenant->slug.'/documents/'.$employee->id.'/unique-certificate.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $admin->id,
        ]);

        // Test filtering by contracts category
        $contractDocs = $employee->documents()
            ->where('document_category_id', $contractsCategory->id)
            ->get();

        expect($contractDocs)->toHaveCount(1);
        expect($contractDocs->first()->name)->toBe('Employment Contract');

        // Test filtering by certifications category
        $certDocs = $employee->documents()
            ->where('document_category_id', $certificationsCategory->id)
            ->get();

        expect($certDocs)->toHaveCount(1);
        expect($certDocs->first()->name)->toBe('Safety Training Certificate');
    });
});

describe('Documents Tab Pagination', function () {
    it('supports pagination for documents list', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $employee = Employee::factory()->create();
        $category = DocumentCategory::where('is_predefined', true)->first();

        // Create 25 documents to exceed pagination limit
        for ($i = 1; $i <= 25; $i++) {
            $document = Document::create([
                'employee_id' => $employee->id,
                'document_category_id' => $category->id,
                'name' => "Document {$i}",
                'original_filename' => "document-{$i}.pdf",
                'stored_filename' => "unique-document-{$i}.pdf",
                'file_path' => $tenant->slug."/documents/{$employee->id}/unique-document-{$i}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'is_company_document' => false,
            ]);

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'stored_filename' => "unique-document-{$i}.pdf",
                'file_path' => $tenant->slug."/documents/{$employee->id}/unique-document-{$i}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'uploaded_by' => $admin->id,
            ]);
        }

        // Test pagination with 20 per page (as per spec)
        $paginatedDocs = $employee->documents()->paginate(20);

        expect($paginatedDocs->total())->toBe(25);
        expect($paginatedDocs->perPage())->toBe(20);
        expect($paginatedDocs->lastPage())->toBe(2);
        expect($paginatedDocs->count())->toBe(20);
    });
});

describe('Documents Tab Permission Checks', function () {
    it('admin user can manage employee documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $admin = createDocumentsTestTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        expect(\Illuminate\Support\Facades\Gate::allows('can-manage-employees'))->toBeTrue();
    });

    it('employee user cannot manage employee documents', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindDocumentsTestTenantContext($tenant);

        $employee_user = createDocumentsTestTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee_user);

        expect(\Illuminate\Support\Facades\Gate::allows('can-manage-employees'))->toBeFalse();
    });
});
