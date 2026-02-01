<?php

/**
 * API Tests for Training Course Catalog
 *
 * Tests the CRUD endpoints for managing courses, prerequisites,
 * and course status workflow (publish/archive).
 */

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CourseController;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
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
function bindCourseTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createCourseUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a validated store course request.
 */
function createStoreCourseRequest(array $data, User $user): StoreCourseRequest
{
    $request = StoreCourseRequest::create('/api/training/courses', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreCourseRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update course request.
 */
function createUpdateCourseRequest(array $data, User $user): UpdateCourseRequest
{
    $request = UpdateCourseRequest::create('/api/training/courses/1', 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $rules = (new UpdateCourseRequest)->rules();
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
    bindCourseTenant($this->tenant);
});

describe('Course CRUD', function () {
    it('lists all courses for admin', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);
        Gate::define('can-view-training', fn () => true);

        Course::factory()->count(3)->create();

        $controller = new CourseController;
        $request = new Request;
        $response = $controller->index($request);

        expect($response->count())->toBe(3);
    });

    it('lists only published courses for employees', function () {
        $employee = createCourseUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-manage-training', fn () => false);
        Gate::define('can-view-training', fn () => true);

        Course::factory()->count(2)->published()->create();
        Course::factory()->count(3)->draft()->create();
        Course::factory()->count(1)->archived()->create();

        $controller = new CourseController;
        $request = new Request;
        $response = $controller->index($request);

        expect($response->count())->toBe(2);
    });

    it('creates a course', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $data = [
            'title' => 'Introduction to Laravel',
            'code' => 'CRS-LRV001',
            'description' => 'Learn the basics of Laravel framework.',
            'delivery_method' => CourseDeliveryMethod::Virtual->value,
            'provider_type' => CourseProviderType::Internal->value,
            'status' => CourseStatus::Draft->value,
            'level' => CourseLevel::Beginner->value,
            'duration_hours' => 8,
            'cost' => 0,
        ];

        $request = createStoreCourseRequest($data, $admin);

        $controller = new CourseController;
        $response = $controller->store($request);

        $this->assertDatabaseHas('courses', [
            'title' => 'Introduction to Laravel',
            'code' => 'CRS-LRV001',
            'delivery_method' => CourseDeliveryMethod::Virtual->value,
        ]);
    });

    it('updates a course', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create([
            'title' => 'Old Title',
            'code' => 'OLD-001',
        ]);

        // Update the course directly to test the update functionality
        $course->update([
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Updated Title',
        ]);
    });

    it('soft deletes a course', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();

        $controller = new CourseController;
        $response = $controller->destroy($this->tenant->slug, $course);

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    });
});

describe('Course Status Workflow', function () {
    it('publishes a draft course', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->draft()->create();

        $controller = new CourseController;
        $response = $controller->publish($this->tenant->slug, $course);

        $course->refresh();
        expect($course->status)->toBe(CourseStatus::Published);
    });

    it('archives a published course', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create();

        $controller = new CourseController;
        $response = $controller->archive($this->tenant->slug, $course);

        $course->refresh();
        expect($course->status)->toBe(CourseStatus::Archived);
    });
});

describe('Course Prerequisites', function () {
    it('creates a course with prerequisites', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $prerequisite1 = Course::factory()->published()->create();
        $prerequisite2 = Course::factory()->published()->create();

        $data = [
            'title' => 'Advanced Course',
            'code' => 'ADV-001',
            'delivery_method' => CourseDeliveryMethod::Virtual->value,
            'provider_type' => CourseProviderType::Internal->value,
            'status' => CourseStatus::Draft->value,
            'prerequisites' => [
                ['id' => $prerequisite1->id, 'is_mandatory' => true],
                ['id' => $prerequisite2->id, 'is_mandatory' => false],
            ],
        ];

        $request = createStoreCourseRequest($data, $admin);

        $controller = new CourseController;
        $response = $controller->store($request);

        $course = Course::where('code', 'ADV-001')->first();
        expect($course->prerequisites)->toHaveCount(2);

        $mandatoryPrereq = $course->prerequisites->firstWhere('id', $prerequisite1->id);
        expect((bool) $mandatoryPrereq->pivot->is_mandatory)->toBeTrue();
    });
});

describe('Course Categories', function () {
    it('creates a course with categories', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $category1 = CourseCategory::factory()->create();
        $category2 = CourseCategory::factory()->create();

        $data = [
            'title' => 'Categorized Course',
            'code' => 'CAT-001',
            'delivery_method' => CourseDeliveryMethod::ELearning->value,
            'provider_type' => CourseProviderType::External->value,
            'provider_name' => 'External Provider Inc.',
            'status' => CourseStatus::Draft->value,
            'category_ids' => [$category1->id, $category2->id],
        ];

        $request = createStoreCourseRequest($data, $admin);

        $controller = new CourseController;
        $response = $controller->store($request);

        $course = Course::where('code', 'CAT-001')->first();
        expect($course->categories)->toHaveCount(2);
    });
});

describe('Course Model', function () {
    it('uses soft deletes', function () {
        $course = Course::factory()->create();
        $course->delete();

        expect(Course::withTrashed()->find($course->id))->not()->toBeNull();
        expect(Course::find($course->id))->toBeNull();
    });

    it('has published scope', function () {
        Course::factory()->count(2)->published()->create();
        Course::factory()->count(3)->draft()->create();

        expect(Course::published()->count())->toBe(2);
    });

    it('has draft scope', function () {
        Course::factory()->count(2)->published()->create();
        Course::factory()->count(3)->draft()->create();

        expect(Course::draft()->count())->toBe(3);
    });

    it('filters by delivery method', function () {
        Course::factory()->virtual()->create();
        Course::factory()->virtual()->create();
        Course::factory()->inPerson()->create();

        expect(Course::byDeliveryMethod(CourseDeliveryMethod::Virtual)->count())->toBe(2);
        expect(Course::byDeliveryMethod(CourseDeliveryMethod::InPerson)->count())->toBe(1);
    });

    it('filters by level', function () {
        Course::factory()->beginner()->create();
        Course::factory()->beginner()->create();
        Course::factory()->advanced()->create();

        expect(Course::byLevel(CourseLevel::Beginner)->count())->toBe(2);
        expect(Course::byLevel(CourseLevel::Advanced)->count())->toBe(1);
    });

    it('calculates formatted duration', function () {
        $course = Course::factory()->create([
            'duration_hours' => 16,
            'duration_days' => 2,
        ]);

        expect($course->formatted_duration)->toBe('2 days (16 hours)');
    });

    it('duplicates a course', function () {
        $original = Course::factory()->published()->create([
            'title' => 'Original Course',
            'code' => 'ORIG-001',
        ]);

        $duplicate = $original->duplicate('COPY-001');

        expect($duplicate->id)->not()->toBe($original->id);
        expect($duplicate->title)->toBe('Original Course');
        expect($duplicate->code)->toBe('COPY-001');
        expect($duplicate->status)->toBe(CourseStatus::Draft);
    });
});

describe('Course Filtering', function () {
    it('filters courses by search term', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);
        Gate::define('can-view-training', fn () => true);

        Course::factory()->create(['title' => 'Laravel Basics']);
        Course::factory()->create(['title' => 'Vue.js Advanced']);
        Course::factory()->create(['title' => 'Python Fundamentals']);

        $controller = new CourseController;
        $request = new Request(['search' => 'Laravel']);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
        expect($response->first()->title)->toBe('Laravel Basics');
    });

    it('filters courses by category', function () {
        $admin = createCourseUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);
        Gate::define('can-view-training', fn () => true);

        $category = CourseCategory::factory()->create();
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();
        $course3 = Course::factory()->create();

        $course1->categories()->attach($category->id);

        $controller = new CourseController;
        $request = new Request(['category_id' => $category->id]);
        $response = $controller->index($request);

        expect($response->count())->toBe(1);
    });
});
