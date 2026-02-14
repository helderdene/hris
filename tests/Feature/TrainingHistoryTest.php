<?php

/**
 * Tests for Training History feature
 *
 * Tests the admin Training History pages including index view,
 * employee-specific history, filtering, and Excel export.
 */

use App\Enums\SessionStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Training\TrainingHistoryController;
use App\Models\Course;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\TrainingHistoryExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTrainingHistoryTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTrainingHistoryUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
function getTrainingHistoryInertiaData(\Inertia\Response $response): array
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('props');
    $property->setAccessible(true);

    return $property->getValue($response);
}

/**
 * Helper to get the Inertia component name.
 */
function getTrainingHistoryComponent(\Inertia\Response $response): string
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
    bindTrainingHistoryTenant($this->tenant);
});

describe('Training History Index Page', function () {
    it('renders the training history page for authorized users', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create();
        $session = TrainingSession::factory()->forCourse($course)->create([
            'status' => SessionStatus::Completed,
            'start_date' => now()->subDays(7),
        ]);
        $employee = Employee::factory()->create();
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->attended()
            ->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);

        expect(getTrainingHistoryComponent($response))->toBe('Training/History/Index');

        $props = getTrainingHistoryInertiaData($response);
        expect($props)->toHaveKey('enrollments');
        expect($props)->toHaveKey('courses');
        expect($props)->toHaveKey('statusOptions');
    });

    it('returns paginated enrollments', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create();
        $session = TrainingSession::factory()->forCourse($course)->create([
            'start_date' => now()->subDays(7),
        ]);
        TrainingEnrollment::factory()
            ->count(30)
            ->forSession($session)
            ->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET');
        app()->instance('request', $request);

        $response = $controller->index($request);
        $props = getTrainingHistoryInertiaData($response);

        // The enrollments prop contains a ResourceCollection that wraps a paginator
        $enrollmentsResource = $props['enrollments'];
        $resourceArray = $enrollmentsResource->toResponse(app('request'))->getData(true);

        expect($resourceArray)->toHaveKey('meta');
        expect($resourceArray['meta']['per_page'])->toBe(25);
    });
});

describe('Training History Filters', function () {
    it('filters by course', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course1 = Course::factory()->published()->create(['title' => 'Course 1']);
        $course2 = Course::factory()->published()->create(['title' => 'Course 2']);
        $session1 = TrainingSession::factory()->forCourse($course1)->create(['start_date' => now()]);
        $session2 = TrainingSession::factory()->forCourse($course2)->create(['start_date' => now()]);

        $enrollment1 = TrainingEnrollment::factory()->forSession($session1)->create();
        $enrollment2 = TrainingEnrollment::factory()->forSession($session2)->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET', ['course_id' => $course1->id]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $props = getTrainingHistoryInertiaData($response);

        $enrollmentIds = collect($props['enrollments']->items())->pluck('id');
        expect($enrollmentIds)->toContain($enrollment1->id);
        expect($enrollmentIds)->not->toContain($enrollment2->id);
    });

    it('filters by enrollment status', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create();
        $session = TrainingSession::factory()->forCourse($course)->create(['start_date' => now()]);

        $attendedEnrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->attended()
            ->create();
        $confirmedEnrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->confirmed()
            ->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET', ['status' => 'attended']);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $props = getTrainingHistoryInertiaData($response);

        $enrollmentIds = collect($props['enrollments']->items())->pluck('id');
        expect($enrollmentIds)->toContain($attendedEnrollment->id);
        expect($enrollmentIds)->not->toContain($confirmedEnrollment->id);
    });

    it('filters by date range', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create();
        $oldSession = TrainingSession::factory()->forCourse($course)->create([
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(2)->addDays(1),
        ]);
        $recentSession = TrainingSession::factory()->forCourse($course)->create([
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(4),
        ]);

        $oldEnrollment = TrainingEnrollment::factory()->forSession($oldSession)->create();
        $recentEnrollment = TrainingEnrollment::factory()->forSession($recentSession)->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET', [
            'date_from' => now()->subDays(10)->format('Y-m-d'),
        ]);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $props = getTrainingHistoryInertiaData($response);

        $enrollmentIds = collect($props['enrollments']->items())->pluck('id');
        expect($enrollmentIds)->toContain($recentEnrollment->id);
        expect($enrollmentIds)->not->toContain($oldEnrollment->id);
    });

    it('filters by search term', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create(['title' => 'Python Training']);
        $session = TrainingSession::factory()->forCourse($course)->create(['start_date' => now()]);

        $johnEmployee = Employee::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $janeEmployee = Employee::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $johnEnrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($johnEmployee)
            ->create();
        $janeEnrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($janeEmployee)
            ->create();

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET', ['search' => 'John']);
        app()->instance('request', $request);

        $response = $controller->index($request);
        $props = getTrainingHistoryInertiaData($response);

        $enrollmentIds = collect($props['enrollments']->items())->pluck('id');
        expect($enrollmentIds)->toContain($johnEnrollment->id);
        expect($enrollmentIds)->not->toContain($janeEnrollment->id);
    });
});

describe('Employee Training History Page', function () {
    it('shows training history for a specific employee', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        $course = Course::factory()->published()->create();
        $session = TrainingSession::factory()->forCourse($course)->create(['start_date' => now()]);

        $employee1Enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee1)
            ->create();
        $employee2Enrollment = TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee2)
            ->create();

        $controller = new TrainingHistoryController;
        $request = Request::create("/training/history/employee/{$employee1->id}", 'GET');
        app()->instance('request', $request);

        $response = $controller->employeeHistory($employee1, $request);
        $props = getTrainingHistoryInertiaData($response);

        expect(getTrainingHistoryComponent($response))->toBe('Training/History/Employee');
        expect($props['employee']['id'])->toBe($employee1->id);

        $enrollmentIds = collect($props['enrollments']->items())->pluck('id');
        expect($enrollmentIds)->toContain($employee1Enrollment->id);
        expect($enrollmentIds)->not->toContain($employee2Enrollment->id);
    });
});

describe('Training History Export', function () {
    it('exports training history to Excel', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->published()->create(['title' => 'Test Course']);
        $session = TrainingSession::factory()->forCourse($course)->create([
            'start_date' => now()->subDays(7),
            'location' => 'Conference Room A',
        ]);
        $employee = Employee::factory()->create([
            'first_name' => 'Export',
            'last_name' => 'Test',
        ]);
        TrainingEnrollment::factory()
            ->forSession($session)
            ->forEmployee($employee)
            ->completed(90.50)
            ->withCertificate('CERT-TEST-001')
            ->create();

        $controller = new TrainingHistoryController;
        $exportService = new TrainingHistoryExportService;
        $request = Request::create('/training/history/export', 'GET');
        app()->instance('request', $request);

        $response = $controller->export($request, $exportService);

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->get('Content-Type'))->toContain('spreadsheetml.sheet');
        expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
    });

    it('exports filtered results', function () {
        $admin = createTrainingHistoryUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course1 = Course::factory()->published()->create(['title' => 'Course 1']);
        $course2 = Course::factory()->published()->create(['title' => 'Course 2']);
        $session1 = TrainingSession::factory()->forCourse($course1)->create(['start_date' => now()]);
        $session2 = TrainingSession::factory()->forCourse($course2)->create(['start_date' => now()]);

        TrainingEnrollment::factory()->forSession($session1)->count(5)->create();
        TrainingEnrollment::factory()->forSession($session2)->count(3)->create();

        $controller = new TrainingHistoryController;
        $exportService = new TrainingHistoryExportService;
        $request = Request::create('/training/history/export', 'GET', [
            'course_id' => $course1->id,
        ]);
        app()->instance('request', $request);

        $response = $controller->export($request, $exportService);

        expect($response->getStatusCode())->toBe(200);
    });
});

describe('Training History Authorization', function () {
    it('denies access to unauthorized users', function () {
        $employee = createTrainingHistoryUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-manage-training', fn () => false);

        $controller = new TrainingHistoryController;
        $request = Request::create('/training/history', 'GET');
        app()->instance('request', $request);

        $controller->index($request);
    })->throws(\Illuminate\Auth\Access\AuthorizationException::class);
});

describe('TrainingEnrollment Model Assessment Features', function () {
    it('returns is_completed true for attended with completed status', function () {
        $enrollment = TrainingEnrollment::factory()->completed()->create();

        expect($enrollment->is_completed)->toBeTrue();
    });

    it('returns is_completed false for attended without completed status', function () {
        $enrollment = TrainingEnrollment::factory()->attended()->create();

        expect($enrollment->is_completed)->toBeFalse();
    });

    it('returns has_certificate true when certificate details exist', function () {
        $enrollment = TrainingEnrollment::factory()
            ->withCertificate('CERT-123')
            ->create();

        expect($enrollment->has_certificate)->toBeTrue();
    });

    it('scopes completed enrollments correctly', function () {
        $completedEnrollment = TrainingEnrollment::factory()->completed()->create();
        $attendedEnrollment = TrainingEnrollment::factory()->attended()->create();
        $confirmedEnrollment = TrainingEnrollment::factory()->confirmed()->create();

        $completedIds = TrainingEnrollment::completed()->pluck('id');

        expect($completedIds)->toContain($completedEnrollment->id);
        expect($completedIds)->not->toContain($attendedEnrollment->id);
        expect($completedIds)->not->toContain($confirmedEnrollment->id);
    });
});
