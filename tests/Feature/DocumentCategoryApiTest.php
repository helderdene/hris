<?php

/**
 * API Tests for Document Category Management
 *
 * Tests the CRUD endpoints for managing document categories,
 * including authorization, validation, and predefined category protection.
 *
 * Note: These tests call controllers directly following the pattern from EmployeeDocumentApiTest.php
 * since tenant subdomain routing requires special handling in tests.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCategoryTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindCategoryTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated StoreCategoryRequest.
 */
function createValidatedCategoryRequest(array $data): StoreCategoryRequest
{
    $request = StoreCategoryRequest::create(
        '/api/document-categories',
        'POST',
        $data
    );

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    // Get the rules and validate
    $rules = (new StoreCategoryRequest)->rules();
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
});

describe('GET /api/document-categories', function () {
    it('returns all categories including predefined and custom', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryTenantContext($tenant);

        $admin = createCategoryTenantUser($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        // Create predefined categories
        DocumentCategory::factory()->predefined()->create(['name' => 'Contracts']);
        DocumentCategory::factory()->predefined()->create(['name' => 'Certifications']);

        // Create custom categories
        DocumentCategory::factory()->custom()->create(['name' => 'Custom Category 1']);
        DocumentCategory::factory()->custom()->create(['name' => 'Custom Category 2']);

        $controller = new DocumentCategoryController;
        $response = $controller->index();

        $data = $response->getData(true);

        expect($data['data'])->toHaveCount(4);

        // Verify both predefined and custom categories are present
        $categoryNames = array_column($data['data'], 'name');
        expect($categoryNames)->toContain('Contracts');
        expect($categoryNames)->toContain('Certifications');
        expect($categoryNames)->toContain('Custom Category 1');
        expect($categoryNames)->toContain('Custom Category 2');
    });
});

describe('POST /api/document-categories', function () {
    it('creates a custom category', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryTenantContext($tenant);

        $hrManager = createCategoryTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $requestData = [
            'name' => 'Training Materials',
            'description' => 'Documents related to employee training and development',
        ];

        $request = createValidatedCategoryRequest($requestData);

        $controller = new DocumentCategoryController;
        $response = $controller->store($request);

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['name'])->toBe('Training Materials');
        expect($data['data']['description'])->toBe('Documents related to employee training and development');
        expect($data['data']['is_predefined'])->toBeFalse();

        // Verify category was created in database
        expect(DocumentCategory::where('name', 'Training Materials')->exists())->toBeTrue();

        $category = DocumentCategory::where('name', 'Training Materials')->first();
        expect($category->is_predefined)->toBeFalse();
    });
});

describe('DELETE /api/document-categories/{category}', function () {
    it('prevents deletion of predefined categories', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryTenantContext($tenant);

        $hrManager = createCategoryTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create a predefined category
        $predefinedCategory = DocumentCategory::factory()->predefined()->create([
            'name' => 'Contracts',
        ]);

        $controller = new DocumentCategoryController;

        // Attempt to delete should be forbidden
        $response = $controller->destroy($predefinedCategory);

        expect($response->getStatusCode())->toBe(403);

        // Verify category still exists
        expect(DocumentCategory::find($predefinedCategory->id))->not->toBeNull();
    });

    it('allows deletion of custom categories', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryTenantContext($tenant);

        $hrManager = createCategoryTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create a custom category
        $customCategory = DocumentCategory::factory()->custom()->create([
            'name' => 'Custom Category',
        ]);

        $categoryId = $customCategory->id;

        $controller = new DocumentCategoryController;
        $response = $controller->destroy($customCategory);

        expect($response->getStatusCode())->toBe(204);

        // Verify category was deleted
        expect(DocumentCategory::find($categoryId))->toBeNull();
    });
});

describe('Category Validation', function () {
    it('rejects duplicate category names', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryTenantContext($tenant);

        $hrManager = createCategoryTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create an existing category
        DocumentCategory::factory()->create(['name' => 'Existing Category']);

        // Attempt to create a category with the same name
        $requestData = [
            'name' => 'Existing Category',
            'description' => 'This should fail',
        ];

        $request = StoreCategoryRequest::create(
            '/api/document-categories',
            'POST',
            $requestData
        );

        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        // Validate using the form request rules
        $formRequest = new StoreCategoryRequest;
        $rules = $formRequest->rules();
        $validator = Validator::make($requestData, $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
    });
});
