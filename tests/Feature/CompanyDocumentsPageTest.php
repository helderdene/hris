<?php

/**
 * Tests for the Company Documents Web Page
 *
 * These tests verify the Inertia page rendering and access control
 * for the company documents feature accessible from tenant navigation.
 *
 * Note: These tests call controllers directly to test permissions and response data
 * without triggering full HTTP response rendering which requires Vite manifest.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\CompanyDocumentController;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCompanyDocPageTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindCompanyDocPageTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to extract Inertia response data without triggering full HTTP response.
 * Uses reflection to access the protected props property.
 */
function getInertiaResponseData(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 * Uses reflection to access the protected component property.
 */
function getInertiaComponent(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    // Set up the main domain for the tests
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Company Documents Page', function () {
    it('renders company documents page with correct component', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocPageTenantContext($tenant);

        $admin = createCompanyDocPageTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create a category and some company documents
        $category = DocumentCategory::factory()->create(['name' => 'Policies']);

        Document::factory()->count(3)->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        // Invoke the controller directly and check the Inertia response
        $controller = new CompanyDocumentController;
        $response = $controller->index('test-tenant');

        // Verify it returns an Inertia response with correct component
        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponent($response))->toBe('CompanyDocuments/Index');
        expect(getInertiaResponseData($response))->toHaveKey('can_manage_company_documents');
    });

    it('restricts document upload to HR users only', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocPageTenantContext($tenant);

        // Test that HR Manager can manage
        $hrManager = createCompanyDocPageTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $controller = new CompanyDocumentController;
        $response = $controller->index('test-tenant');

        // HR Manager should have manage permission
        $data = getInertiaResponseData($response);
        expect($data['can_manage_company_documents'])->toBeTrue();

        // Test that Employee cannot manage
        $employee = createCompanyDocPageTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $response = $controller->index('test-tenant');
        $data = getInertiaResponseData($response);

        // Employee should not have manage permission
        expect($data['can_manage_company_documents'])->toBeFalse();
    });

    it('allows all tenant users to view page', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocPageTenantContext($tenant);

        // Test with Employee role (lowest access level)
        $employee = createCompanyDocPageTenantUser($tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        $category = DocumentCategory::factory()->create();
        Document::factory()->create([
            'employee_id' => null,
            'document_category_id' => $category->id,
            'is_company_document' => true,
        ]);

        // Employee should have view permission
        expect(Gate::allows('can-view-company-documents'))->toBeTrue();

        $controller = new CompanyDocumentController;
        $response = $controller->index('test-tenant');

        // Employee should be able to access the page
        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getInertiaComponent($response))->toBe('CompanyDocuments/Index');
    });

    it('passes correct permission flag to frontend based on role', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCompanyDocPageTenantContext($tenant);

        // Test HR Staff gets manage permission
        $hrStaff = createCompanyDocPageTenantUser($tenant, TenantUserRole::HrStaff);
        $this->actingAs($hrStaff);

        $controller = new CompanyDocumentController;
        $response = $controller->index('test-tenant');
        $data = getInertiaResponseData($response);

        // Check that the Inertia response includes the permission
        expect($data)->toHaveKey('can_manage_company_documents');
        expect($data['can_manage_company_documents'])->toBeTrue();

        // Test Supervisor does not get manage permission
        $supervisor = createCompanyDocPageTenantUser($tenant, TenantUserRole::Supervisor);
        $this->actingAs($supervisor);

        $response = $controller->index('test-tenant');
        $data = getInertiaResponseData($response);

        expect($data)->toHaveKey('can_manage_company_documents');
        expect($data['can_manage_company_documents'])->toBeFalse();
    });
});
