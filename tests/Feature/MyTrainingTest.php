<?php

/**
 * Tests for My Training (Employee Self-Service)
 *
 * Tests the employee-facing training catalog pages for browsing
 * published courses.
 */

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\TenantUserRole;
use App\Http\Controllers\MyTrainingController;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindMyTrainingTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createMyTrainingUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to extract Inertia response data.
 */
function getMyTrainingInertiaData(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 */
function getMyTrainingComponent(\Inertia\Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('component');
    $property->setAccessible(true);

    return $property->getValue($response);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant migrations
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Create a test tenant and bind it
    $this->tenant = Tenant::factory()->create(['slug' => 'testco']);
    bindMyTrainingTenant($this->tenant);
});

describe('My Training Index Page', function () {
    it('renders the training catalog page', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        Course::factory()->count(3)->published()->create();

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getMyTrainingComponent($response))->toBe('My/Training/Index');

        $data = getMyTrainingInertiaData($response);
        expect($data)->toHaveKey('courses');
        expect($data)->toHaveKey('categories');
        expect($data)->toHaveKey('filters');
        expect($data)->toHaveKey('deliveryMethodOptions');
        expect($data)->toHaveKey('levelOptions');
    });

    it('shows only published courses', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        Course::factory()->count(2)->published()->create();
        Course::factory()->count(3)->draft()->create();
        Course::factory()->count(1)->archived()->create();

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['courses']))->toBe(2);
    });

    it('filters by delivery method', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        Course::factory()->published()->virtual()->create();
        Course::factory()->published()->virtual()->create();
        Course::factory()->published()->inPerson()->create();

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET', [
            'delivery_method' => CourseDeliveryMethod::Virtual->value,
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['courses']))->toBe(2);
    });

    it('filters by level', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        Course::factory()->published()->beginner()->create();
        Course::factory()->published()->beginner()->create();
        Course::factory()->published()->advanced()->create();

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET', [
            'level' => CourseLevel::Beginner->value,
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['courses']))->toBe(2);
    });

    it('filters by category', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        $category = CourseCategory::factory()->create();
        $course1 = Course::factory()->published()->create();
        $course2 = Course::factory()->published()->create();
        Course::factory()->published()->create();

        $course1->categories()->attach($category->id);

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET', [
            'category_id' => $category->id,
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['courses']))->toBe(1);
    });

    it('searches by title or code', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        Course::factory()->published()->create(['title' => 'Laravel Basics', 'code' => 'LRV-001']);
        Course::factory()->published()->create(['title' => 'Vue.js Advanced', 'code' => 'VUE-001']);
        Course::factory()->published()->create(['title' => 'React Fundamentals', 'code' => 'RCT-001']);

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET', [
            'search' => 'Laravel',
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['courses']))->toBe(1);
        expect($data['courses'][0]['title'])->toBe('Laravel Basics');
    });

    it('shows only active categories with published courses', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        // Create active categories with published courses
        $activeCategory1 = CourseCategory::factory()->active()->create();
        $activeCategory2 = CourseCategory::factory()->active()->create();
        CourseCategory::factory()->count(3)->inactive()->create();

        // Categories only show up if they have published courses
        $course1 = Course::factory()->published()->create();
        $course2 = Course::factory()->published()->create();
        $course1->categories()->attach($activeCategory1->id);
        $course2->categories()->attach($activeCategory2->id);

        $controller = new MyTrainingController;
        $request = Request::create('/my/training', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);
        $data = getMyTrainingInertiaData($response);

        expect(count($data['categories']))->toBe(2);
    });
});

describe('My Training Show Page', function () {
    it('renders the course detail page', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        $course = Course::factory()->published()->create();

        $controller = new MyTrainingController;
        $request = Request::create("/my/training/courses/{$course->id}", 'GET');
        app()->instance('request', $request);

        $response = $controller->show($course);

        expect($response)->toBeInstanceOf(\Inertia\Response::class);
        expect(getMyTrainingComponent($response))->toBe('My/Training/Show');

        $data = getMyTrainingInertiaData($response);
        expect($data)->toHaveKey('course');
        expect($data['course']['id'])->toBe($course->id);
    });

    it('loads course with categories', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        $category = CourseCategory::factory()->create(['name' => 'Test Category']);
        $course = Course::factory()->published()->create();
        $course->categories()->attach($category->id);

        $controller = new MyTrainingController;
        $request = Request::create("/my/training/courses/{$course->id}", 'GET');
        app()->instance('request', $request);

        $response = $controller->show($course);
        $data = getMyTrainingInertiaData($response);

        expect($data['course']['categories'])->toHaveCount(1);
        expect($data['course']['categories'][0]['name'])->toBe('Test Category');
    });

    it('loads course with prerequisites', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        $prerequisite = Course::factory()->published()->create(['title' => 'Prerequisite Course']);
        $course = Course::factory()->published()->create();
        $course->prerequisites()->attach($prerequisite->id, ['is_mandatory' => true]);

        // Reload the course to get fresh data with prerequisites
        $course = Course::with('prerequisites')->find($course->id);

        $controller = new MyTrainingController;
        $request = Request::create("/my/training/courses/{$course->id}", 'GET');
        app()->instance('request', $request);

        $response = $controller->show($course);
        $data = getMyTrainingInertiaData($response);

        expect($data['course']['prerequisites'])->toHaveCount(1);
        expect($data['course']['prerequisites'][0]['title'])->toBe('Prerequisite Course');
    });

    it('only shows published courses', function () {
        $employee = createMyTrainingUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-view-training', fn () => true);

        $draftCourse = Course::factory()->draft()->create();

        $controller = new MyTrainingController;
        $request = Request::create("/my/training/courses/{$draftCourse->id}", 'GET');
        app()->instance('request', $request);

        expect(fn () => $controller->show($draftCourse))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    });
});
