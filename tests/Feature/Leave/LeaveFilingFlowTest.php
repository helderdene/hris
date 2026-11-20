<?php

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    $this->api = "http://{$tenant->slug}.kasamahr.test/api/leave-applications";

    $this->member = $member = function (TenantUserRole $role) use ($tenant): User {
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => $role->value, 'invited_at' => now(), 'invitation_accepted_at' => now()]);

        return $user;
    };

    $this->head = $head = Employee::factory()->create(['employment_status' => EmploymentStatus::Active, 'user_id' => $member(TenantUserRole::Admin)->id]);
    $department = Department::factory()->create(['department_head_id' => $head->id]);

    $this->employee = Employee::factory()->create([
        'department_id' => $department->id,
        'employment_status' => EmploymentStatus::Active,
        'user_id' => $member(TenantUserRole::Employee)->id,
    ]);
    $this->leaveType = LeaveType::factory()->create();

    LeaveBalance::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'year' => now()->addDays(7)->year,
        'earned' => 15,
        'used' => 0,
        'pending' => 0,
    ]);

    $this->actingAs(User::find($this->employee->user_id));

    $this->payload = [
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => now()->addDays(7)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'is_half_day_start' => '0',
        'is_half_day_end' => '0',
        'reason' => 'Family matter',
    ];
});

/*
 * The leave form creates the application, then submits it using the id from the
 * create response. That id is read from the top level of the JSON body.
 */
it('files a leave in the two calls the form makes', function () {
    $id = $this->postJson($this->api, $this->payload)
        ->assertCreated()
        ->assertJsonPath('status', LeaveApplicationStatus::Draft->value)
        ->json('id');

    expect($id)->toBeInt();

    $this->postJson("{$this->api}/{$id}/submit")->assertOk();

    expect(LeaveApplication::find($id)->status)->toBe(LeaveApplicationStatus::Pending);
});

it('keeps the draft and returns a readable error when submission is refused', function () {
    $id = $this->postJson($this->api, $this->payload)->assertCreated()->json('id');

    LeaveBalance::query()->update(['earned' => 0]);

    $response = $this->postJson("{$this->api}/{$id}/submit")->assertUnprocessable();

    expect(collect($response->json('errors'))->flatten()->first())->toBeString()->not->toBeEmpty()
        ->and(LeaveApplication::find($id)->status)->toBe(LeaveApplicationStatus::Draft);
});

describe('access control', function () {
    beforeEach(function () {
        $this->applicationId = $this->postJson($this->api, [...$this->payload, 'attachment' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')])
            ->assertCreated()
            ->json('id');

        $this->colleague = Employee::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'user_id' => ($this->member)(TenantUserRole::Employee)->id,
        ]);
    });

    it('stops a colleague from reading, changing or cancelling someone else\'s leave', function () {
        $this->actingAs(User::find($this->colleague->user_id));
        $url = "{$this->api}/{$this->applicationId}";

        $this->getJson($url)->assertForbidden();
        $this->get("{$url}/attachment")->assertForbidden();
        $this->putJson($url, $this->payload)->assertForbidden();
        $this->postJson("{$url}/submit")->assertForbidden();
        $this->postJson("{$url}/cancel")->assertForbidden();
        $this->deleteJson($url)->assertForbidden();

        expect(LeaveApplication::find($this->applicationId)->status)->toBe(LeaveApplicationStatus::Draft);
    });

    it('stops an employee from filing leave as someone else', function () {
        $this->actingAs(User::find($this->colleague->user_id));

        $this->postJson($this->api, $this->payload)->assertForbidden();

        // An over-balance request must be refused outright, not answered with the colleague's balance.
        $this->postJson($this->api, [...$this->payload, 'end_date' => now()->addDays(60)->toDateString()])
            ->assertForbidden();

        expect(LeaveApplication::count())->toBe(1);
    });

    it('guards the leave application page the same way', function () {
        $page = str_replace('/api/leave-applications', '/leave/applications', $this->api)."/{$this->applicationId}";

        $this->get($page)->assertOk();

        $this->actingAs(User::find($this->colleague->user_id));
        $this->get($page)->assertForbidden();
    });

    it('lets the owner read, update, submit and cancel their own leave', function () {
        $url = "{$this->api}/{$this->applicationId}";

        $this->getJson($url)->assertOk();
        $this->get("{$url}/attachment")->assertOk();
        $this->putJson($url, [...$this->payload, 'reason' => 'Updated'])->assertOk();
        $this->postJson("{$url}/submit")->assertOk();
        $this->postJson("{$url}/cancel")->assertOk();
    });

    it('lets HR act on any leave and file on an employee\'s behalf', function () {
        $this->actingAs(($this->member)(TenantUserRole::HrManager));
        $url = "{$this->api}/{$this->applicationId}";

        $this->getJson($url)->assertOk();
        $this->postJson("{$url}/submit")->assertOk();
        $this->postJson($this->api, [...$this->payload, 'start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(20)->toDateString()])
            ->assertCreated();
    });

    it('lets the assigned approver read the leave and its attachment but not change it', function () {
        $url = "{$this->api}/{$this->applicationId}";
        $this->postJson("{$url}/submit")->assertOk();

        $approver = ($this->member)(TenantUserRole::Supervisor);
        $this->head->update(['user_id' => $approver->id]);
        $this->actingAs($approver);

        $this->getJson($url)->assertOk();
        $this->get("{$url}/attachment")->assertOk();
        $this->postJson("{$url}/cancel")->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
    });
});
