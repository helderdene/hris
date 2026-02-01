<?php

/**
 * Feature Tests for Category Management UI
 *
 * Tests the frontend integration for creating, listing, and deleting document categories.
 * These tests verify that the category management modal works correctly and prevents
 * accidental deletion of categories.
 *
 * Note: These tests focus on the API interactions that the UI relies on,
 * following the pattern from DocumentCategoryApiTest.php.
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
function createCategoryManagementTenantUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function bindCategoryManagementTenantContext(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a validated StoreCategoryRequest.
 */
function createCategoryManagementRequest(array $data): StoreCategoryRequest
{
    $request = StoreCategoryRequest::create(
        '/api/document-categories',
        'POST',
        $data
    );

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $rules = (new StoreCategoryRequest)->rules();
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
});

describe('Create Category Modal', function () {
    it('creates a new category via modal form submission', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $requestData = [
            'name' => 'New Custom Category',
            'description' => 'Description for the custom category',
        ];

        $request = createCategoryManagementRequest($requestData);
        $controller = new DocumentCategoryController;
        $response = $controller->store($request, 'test-tenant');

        expect($response->getStatusCode())->toBe(201);

        $data = $response->getData(true);
        expect($data['data']['name'])->toBe('New Custom Category');
        expect($data['data']['description'])->toBe('Description for the custom category');
        expect($data['data']['is_predefined'])->toBeFalse();

        // Verify category exists in database
        $category = DocumentCategory::where('name', 'New Custom Category')->first();
        expect($category)->not->toBeNull();
        expect($category->is_predefined)->toBeFalse();
    });

    it('validates required name field in create modal', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        $requestData = [
            'name' => '',
            'description' => 'Some description',
        ];

        $formRequest = new StoreCategoryRequest;
        $rules = $formRequest->rules();
        $validator = Validator::make($requestData, $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
    });
});

describe('Category Appears in List After Creation', function () {
    it('newly created category appears in the categories list', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create initial categories
        DocumentCategory::factory()->predefined()->create(['name' => 'Contracts']);
        DocumentCategory::factory()->predefined()->create(['name' => 'Certifications']);

        // Get initial count
        $controller = new DocumentCategoryController;
        $initialResponse = $controller->index('test-tenant');
        $initialData = $initialResponse->getData(true);
        $initialCount = count($initialData['data']);

        expect($initialCount)->toBe(2);

        // Create a new custom category
        $requestData = [
            'name' => 'Newly Created Category',
            'description' => 'Created via modal',
        ];

        $request = createCategoryManagementRequest($requestData);
        $createResponse = $controller->store($request, 'test-tenant');

        expect($createResponse->getStatusCode())->toBe(201);

        // Fetch list again
        $updatedResponse = $controller->index('test-tenant');
        $updatedData = $updatedResponse->getData(true);

        expect(count($updatedData['data']))->toBe($initialCount + 1);

        // Verify the new category is in the list
        $categoryNames = array_column($updatedData['data'], 'name');
        expect($categoryNames)->toContain('Newly Created Category');
    });
});

describe('Delete Confirmation Prevents Accidental Deletion', function () {
    it('predefined categories cannot be deleted even with confirmation', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create a predefined category
        $predefinedCategory = DocumentCategory::factory()->predefined()->create([
            'name' => 'Protected Category',
        ]);

        $controller = new DocumentCategoryController;
        $response = $controller->destroy('test-tenant', $predefinedCategory);

        // Should return 403 Forbidden
        expect($response->getStatusCode())->toBe(403);

        $data = $response->getData(true);
        expect($data['message'])->toBe('Predefined categories cannot be deleted.');

        // Category should still exist
        expect(DocumentCategory::find($predefinedCategory->id))->not->toBeNull();
    });

    it('custom categories can be deleted after confirmation', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create a custom category
        $customCategory = DocumentCategory::factory()->custom()->create([
            'name' => 'Deletable Category',
        ]);

        $categoryId = $customCategory->id;

        $controller = new DocumentCategoryController;
        $response = $controller->destroy('test-tenant', $customCategory);

        // Should return 204 No Content
        expect($response->getStatusCode())->toBe(204);

        // Category should be deleted
        expect(DocumentCategory::find($categoryId))->toBeNull();
    });

    it('category list updates after deletion', function () {
        $tenant = Tenant::factory()->create(['slug' => 'test-tenant']);
        bindCategoryManagementTenantContext($tenant);

        $hrManager = createCategoryManagementTenantUser($tenant, TenantUserRole::HrManager);
        $this->actingAs($hrManager);

        // Create categories
        $customCategory = DocumentCategory::factory()->custom()->create([
            'name' => 'Category To Delete',
        ]);
        DocumentCategory::factory()->custom()->create(['name' => 'Category To Keep']);

        $controller = new DocumentCategoryController;

        // Get initial list
        $initialResponse = $controller->index('test-tenant');
        $initialData = $initialResponse->getData(true);
        $initialCount = count($initialData['data']);

        // Delete the category
        $controller->destroy('test-tenant', $customCategory);

        // Get updated list
        $updatedResponse = $controller->index('test-tenant');
        $updatedData = $updatedResponse->getData(true);

        expect(count($updatedData['data']))->toBe($initialCount - 1);

        // Verify deleted category is not in the list
        $categoryNames = array_column($updatedData['data'], 'name');
        expect($categoryNames)->not->toContain('Category To Delete');
        expect($categoryNames)->toContain('Category To Keep');
    });
});
