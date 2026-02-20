<?php

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Models\WorkLocation;
use App\Notifications\VisitorApproved;
use App\Notifications\VisitorArrived;
use App\Notifications\VisitorCheckedOut;
use App\Notifications\VisitorPreRegistered;
use App\Notifications\VisitorRegistrationRequested;
use App\Notifications\VisitorRejected;
use App\Services\Visitor\VisitorCheckInService;
use App\Services\Visitor\VisitorRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Helper to create an admin user attached to a tenant.
 */
function createVisitorNotificationAdmin(Tenant $tenant): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create an employee with an associated user account.
 */
function createEmployeeWithUser(): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    return [$employee, $user];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    config(['database.connections.tenant' => config('database.connections.sqlite')]);
    DB::connection('tenant')->setPdo(DB::connection()->getPdo());
    DB::connection('tenant')->setReadPdo(DB::connection()->getReadPdo());
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
});

describe('Visitor Notifications', function () {
    it('sends registration requested notification to host', function () {
        Notification::fake();
        Queue::fake();

        [$hostEmployee, $hostUser] = createEmployeeWithUser();
        $location = WorkLocation::factory()->create();

        $service = app(VisitorRegistrationService::class);
        $visit = $service->registerFromPublicPage(
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '09171234567',
                'company' => 'Acme Corp',
            ],
            [
                'work_location_id' => $location->id,
                'host_employee_id' => $hostEmployee->id,
                'purpose' => 'Business meeting',
                'expected_at' => now()->addDay(),
            ]
        );

        Notification::assertSentTo($hostUser, VisitorRegistrationRequested::class);
    });

    it('sends approved notification to visitor on approval', function () {
        Notification::fake();
        Queue::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->hostApproved()
            ->create(['visitor_id' => $visitor->id]);

        $admin = createVisitorNotificationAdmin($this->tenant);

        $service = app(VisitorRegistrationService::class);
        $service->adminApprove($visit, $admin);

        Notification::assertSentOnDemand(VisitorApproved::class);
    });

    it('sends rejected notification to visitor', function () {
        Notification::fake();
        Queue::fake();

        $visitor = Visitor::factory()->create(['email' => 'visitor@example.com']);
        $visit = VisitorVisit::factory()
            ->pendingApproval()
            ->create(['visitor_id' => $visitor->id]);

        $admin = createVisitorNotificationAdmin($this->tenant);

        $service = app(VisitorRegistrationService::class);
        $service->reject($visit, $admin, 'Not authorized');

        Notification::assertSentOnDemand(VisitorRejected::class);
    });

    it('sends pre-registered notification to visitor', function () {
        Notification::fake();
        Queue::fake();

        $visitor = Visitor::factory()->create(['email' => 'preregvisitor@example.com']);
        $location = WorkLocation::factory()->create();
        $admin = createVisitorNotificationAdmin($this->tenant);

        $service = app(VisitorRegistrationService::class);
        $service->preRegister(
            $visitor,
            [
                'work_location_id' => $location->id,
                'purpose' => 'Site visit',
                'expected_at' => now()->addDays(2),
            ],
            $admin
        );

        Notification::assertSentOnDemand(VisitorPreRegistered::class);
    });

    it('sends arrived notification to host on check-in', function () {
        Notification::fake();

        [$hostEmployee, $hostUser] = createEmployeeWithUser();
        $visitor = Visitor::factory()->create();
        $visit = VisitorVisit::factory()
            ->approved()
            ->create([
                'visitor_id' => $visitor->id,
                'host_employee_id' => $hostEmployee->id,
            ]);

        $admin = createVisitorNotificationAdmin($this->tenant);

        $service = app(VisitorCheckInService::class);
        $service->checkInManual($visit, $admin);

        Notification::assertSentTo($hostUser, VisitorArrived::class);
    });

    it('sends checked-out notification to host on check-out', function () {
        Notification::fake();

        [$hostEmployee, $hostUser] = createEmployeeWithUser();
        $visitor = Visitor::factory()->create();
        $visit = VisitorVisit::factory()
            ->checkedIn()
            ->create([
                'visitor_id' => $visitor->id,
                'host_employee_id' => $hostEmployee->id,
            ]);

        $service = app(VisitorCheckInService::class);
        $service->checkOut($visit);

        Notification::assertSentTo($hostUser, VisitorCheckedOut::class);
    });
});
