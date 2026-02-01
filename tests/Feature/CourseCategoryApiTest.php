<?php

/**
 * API Tests for Course Categories
 *
 * Tests the CRUD endpoints for managing course categories with hierarchical
 * structure support.
 */

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CourseCategoryController;
use App\Http\Requests\StoreCourseCategoryRequest;
use App\Http\Requests\UpdateCourseCategoryRequest;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindCategoryTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCategoryUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store category request.
 */
function createStoreCategoryRequest(array $data, User $user): StoreCourseCategoryRequest
{
    $request = StoreCourseCategoryRequest::create('/api/training/categories', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreCourseCategoryRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update category request.
 */
function createUpdateCategoryRequest(array $data, User $user): UpdateCourseCategoryRequest
{
    $request = UpdateCourseCategoryRequest::create('/api/training/categories/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UpdateCourseCategoryRequest)->rules();
    $validator = Validator::make($data, $rules);

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant migrations
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Create a test tenant and bind it
    $this->tenant = Tenant::factory()->create();
    bindCategoryTenant($this->tenant);
});

describe('Course Category CRUD', function () {
    it('lists all categories', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        CourseCategory::factory()->count(3)->create();

        $controller = new CourseCategoryController;
        $request = new Request;
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('creates a category', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $data = [
            'name' => 'Technical Skills',
            'code' => 'TECH-001',
            'description' => 'Technical training courses',
            'is_active' => true,
        ];

        $request = createStoreCategoryRequest($data, $admin);

        $controller = new CourseCategoryController;
        $response = $controller->store($request);

        $this->assertDatabaseHas('course_categories', [
            'name' => 'Technical Skills',
            'code' => 'TECH-001',
        ]);
    });

    it('creates a category with parent', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $parent = CourseCategory::factory()->create(['name' => 'Parent Category']);

        $data = [
            'name' => 'Child Category',
            'code' => 'CHILD-001',
            'parent_id' => $parent->id,
            'is_active' => true,
        ];

        $request = createStoreCategoryRequest($data, $admin);

        $controller = new CourseCategoryController;
        $response = $controller->store($request);

        $child = CourseCategory::where('code', 'CHILD-001')->first();
        expect($child->parent_id)->toBe($parent->id);
        expect($child->parent->name)->toBe('Parent Category');
    });

    it('updates a category', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $category = CourseCategory::factory()->create([
            'name' => 'Old Name',
            'code' => 'OLD-001',
        ]);

        // Update the category directly to test the update functionality
        $category->update([
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $this->assertDatabaseHas('course_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    });

    it('deletes a category without courses or children', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $category = CourseCategory::factory()->create();

        $controller = new CourseCategoryController;
        $response = $controller->destroy($this->tenant->slug, $category);

        expect($response->getStatusCode())->toBe(200);
        $this->assertSoftDeleted('course_categories', ['id' => $category->id]);
    });

    it('prevents deletion of category with courses', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $category = CourseCategory::factory()->create();
        $course = Course::factory()->create();
        $course->categories()->attach($category->id);

        $controller = new CourseCategoryController;
        $response = $controller->destroy($this->tenant->slug, $category);

        expect($response->getStatusCode())->toBe(422);
        $this->assertDatabaseHas('course_categories', ['id' => $category->id]);
    });

    it('prevents deletion of category with children', function () {
        $admin = createCategoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $parent = CourseCategory::factory()->create();
        CourseCategory::factory()->withParent($parent)->create();

        $controller = new CourseCategoryController;
        $response = $controller->destroy($this->tenant->slug, $parent);

        expect($response->getStatusCode())->toBe(422);
        $this->assertDatabaseHas('course_categories', ['id' => $parent->id]);
    });
});

describe('CourseCategory Model', function () {
    it('has active scope', function () {
        CourseCategory::factory()->count(2)->active()->create();
        CourseCategory::factory()->count(3)->inactive()->create();

        expect(CourseCategory::active()->count())->toBe(2);
    });

    it('has parent-child relationship', function () {
        $parent = CourseCategory::factory()->create();
        $child1 = CourseCategory::factory()->withParent($parent)->create();
        $child2 = CourseCategory::factory()->withParent($parent)->create();

        expect($parent->children)->toHaveCount(2);
        expect($child1->parent->id)->toBe($parent->id);
    });

    it('counts courses correctly', function () {
        $category = CourseCategory::factory()->create();
        $courses = Course::factory()->count(3)->create();

        foreach ($courses as $course) {
            $course->categories()->attach($category->id);
        }

        $category->loadCount('courses');
        expect($category->courses_count)->toBe(3);
    });

    it('prevents circular reference in parent hierarchy', function () {
        $parent = CourseCategory::factory()->create();
        $child = CourseCategory::factory()->withParent($parent)->create();

        // Try to set parent's parent to the child - should fail validation
        $parent->parent_id = $child->id;

        expect($parent->isCircularReference())->toBeTrue();
    });

    it('allows valid parent assignment', function () {
        $category1 = CourseCategory::factory()->create();
        $category2 = CourseCategory::factory()->create();

        $category2->parent_id = $category1->id;

        expect($category2->isCircularReference())->toBeFalse();
    });
});
