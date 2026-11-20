<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Models\CompetencyEvaluation;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LoanApplication;
use App\Models\OvertimeRequest;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Notification::fake();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $tenant = Tenant::factory()->create();
    app()->instance('tenant', $tenant);
    $this->base = "http://{$tenant->slug}.kasamahr.test";

    $this->member = function (TenantUserRole $role) use ($tenant): User {
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => $role->value, 'invited_at' => now(), 'invitation_accepted_at' => now()]);

        return $user;
    };

    $employeeFor = fn (TenantUserRole $role) => Employee::factory()->create([
        'employment_status' => EmploymentStatus::Active,
        'user_id' => ($this->member)($role)->id,
    ]);

    $this->owner = $employeeFor(TenantUserRole::Employee);
    $this->colleague = $employeeFor(TenantUserRole::Employee);
    $this->hr = ($this->member)(TenantUserRole::HrManager);

    $this->as = fn (Employee|User $who) => $this->actingAs($who instanceof User ? $who : User::find($who->user_id));
});

describe('daily time records', function () {
    beforeEach(function () {
        $this->record = DailyTimeRecord::factory()->create([
            'employee_id' => $this->owner->id,
            'overtime_minutes' => 120,
            'overtime_approved' => false,
        ]);
        $this->dtr = "{$this->base}/api/time-attendance/dtr";
    });

    it('refuses every time-record action to a plain employee, even on their own record', function (string $method, string $path) {
        ($this->as)($this->owner);

        $this->json($method, $this->dtr.str_replace(['{record}', '{employee}'], [$this->record->id, $this->owner->id], $path))
            ->assertForbidden();

        expect($this->record->refresh()->overtime_approved)->toBeFalse();
    })->with([
        ['GET', ''],
        ['GET', '/needs-review'],
        ['GET', '/pending-overtime'],
        ['GET', '/record/{record}'],
        ['POST', '/record/{record}/approve-overtime'],
        ['POST', '/record/{record}/deny-overtime'],
        ['POST', '/record/{record}/resolve-review'],
        ['POST', '/record/{record}/remarks'],
        ['GET', '/employee/{employee}'],
        ['GET', '/employee/{employee}/summary'],
        ['POST', '/employee/{employee}/calculate'],
        ['POST', '/employee/{employee}/calculate-range'],
    ]);

    it('still lets HR approve overtime', function () {
        ($this->as)($this->hr);

        $this->postJson("{$this->dtr}/record/{$this->record->id}/approve-overtime")->assertOk();

        expect($this->record->refresh()->overtime_approved)->toBeTrue();
    });
});

describe('leave balances', function () {
    beforeEach(function () {
        $this->balance = LeaveBalance::factory()->create(['employee_id' => $this->owner->id]);
        $this->api = "{$this->base}/api/organization/leave-balances";
    });

    it('refuses the balance API and page to a plain employee', function (string $method, string $path) {
        ($this->as)($this->colleague);

        $url = $this->base.str_replace(['{balance}', '{employee}'], [$this->balance->id, $this->owner->id], $path);

        $this->json($method, $url)->assertForbidden();
    })->with([
        ['GET', '/api/organization/leave-balances'],
        ['GET', '/api/organization/leave-balances/summary'],
        ['GET', '/api/organization/leave-balances/{balance}'],
        ['POST', '/api/organization/leave-balances/{balance}/adjust'],
        ['POST', '/api/organization/leave-balances/initialize'],
        ['POST', '/api/organization/leave-balances/process-year-end'],
        ['GET', '/api/employees/{employee}/leave-balances'],
        ['GET', '/organization/leave-balances'],
    ]);

    it('still lets HR read balances', function () {
        ($this->as)($this->hr);

        $this->getJson("{$this->api}/{$this->balance->id}")->assertOk();
        $this->getJson("{$this->base}/api/employees/{$this->owner->id}/leave-balances")->assertOk();
    });
});

describe('overtime requests and loan applications', function () {
    beforeEach(function () {
        $this->records = [
            'overtime-requests' => OvertimeRequest::factory()->draft()->create(['employee_id' => $this->owner->id]),
            'loan-applications' => LoanApplication::factory()->draft()->create(['employee_id' => $this->owner->id]),
        ];
    });

    it('refuses a colleague every action on someone else\'s record', function (string $resource) {
        ($this->as)($this->colleague);
        $record = $this->records[$resource];
        $url = "{$this->base}/api/{$resource}/{$record->id}";

        $this->getJson($url)->assertForbidden();
        $this->putJson($url, [])->assertForbidden();
        $this->postJson("{$url}/submit")->assertForbidden();
        $this->postJson("{$url}/cancel")->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->postJson("{$this->base}/api/{$resource}", ['employee_id' => $this->owner->id])->assertForbidden();

        expect($record->fresh())->not->toBeNull()
            ->and($record->fresh()->status)->toBe($record->status)
            ->and($record::query()->count())->toBe(1);
    })->with(['overtime-requests', 'loan-applications']);

    it('guards the overtime request page the same way', function () {
        $page = "{$this->base}/overtime/requests/{$this->records['overtime-requests']->id}";

        ($this->as)($this->colleague);
        $this->get($page)->assertForbidden();

        ($this->as)($this->owner);
        $this->get($page)->assertOk();
    });

    it('lets the owner and HR read the record', function (string $resource) {
        $url = "{$this->base}/api/{$resource}/{$this->records[$resource]->id}";

        ($this->as)($this->owner);
        $this->getJson($url)->assertOk();

        ($this->as)($this->hr);
        $this->getJson($url)->assertOk();
    })->with(['overtime-requests', 'loan-applications']);

    it('does not refuse an employee filing for themselves', function (string $resource) {
        ($this->as)($this->owner);

        // An incomplete payload gets past authorization and fails later, in validation.
        $status = $this->postJson("{$this->base}/api/{$resource}", ['employee_id' => $this->owner->id])->status();

        expect($status)->not->toBe(403);
    })->with(['overtime-requests', 'loan-applications']);
});

it('lets only the evaluated employee submit a competency self-rating', function () {
    $evaluation = CompetencyEvaluation::factory()->create([
        'performance_cycle_participant_id' => PerformanceCycleParticipant::factory()->create(['employee_id' => $this->owner->id])->id,
        'self_rating' => null,
    ]);
    $url = "{$this->base}/api/performance/competency-evaluations/{$evaluation->id}/self-rating";

    ($this->as)($this->colleague);
    $this->postJson($url, ['self_rating' => 1])->assertForbidden();
    expect($evaluation->refresh()->self_rating)->toBeNull();

    ($this->as)($this->owner);
    $this->postJson($url, ['self_rating' => 4])->assertOk();
    expect($evaluation->refresh()->self_rating)->toBe(4);
});

it('hides the leave reason on the calendar from colleagues', function () {
    $leave = LeaveApplication::factory()->approved()->create([
        'employee_id' => $this->owner->id,
        'start_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(3)->toDateString(),
        'reason' => 'Private medical appointment',
    ]);
    $url = "{$this->base}/api/leave-calendar?year=".now()->year.'&month='.now()->month;

    ($this->as)($this->colleague);
    $this->getJson($url)->assertOk()->assertDontSee('Private medical appointment');

    ($this->as)($this->owner);
    $this->getJson($url)->assertOk()->assertSee('Private medical appointment');

    ($this->as)($this->hr);
    $this->getJson($url)->assertOk()->assertSee('Private medical appointment');

    expect($leave->exists)->toBeTrue();
});
