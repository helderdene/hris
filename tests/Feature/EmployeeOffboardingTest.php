<?php

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Enums\EmploymentStatus;
use App\Enums\OffboardingStepStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeOffboardingController;
use App\Http\Requests\ClearanceItemRequest;
use App\Http\Requests\SeparateEmployeeRequest;
use App\Http\Requests\StartOffboardingRequest;
use App\Http\Requests\UpdateOffboardingRequest;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeOffboarding;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OffboardingNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/**
 * Build a validated form request and act as a tenant user with the given role.
 *
 * @template T of FormRequest
 *
 * @param  class-string<T>  $class
 * @return T
 */
function offboardingRequest(string $class, array $data, string $method = 'POST'): FormRequest
{
    $request = $class::create('/employees/1/offboarding', $method, $data);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => auth()->user());
    $request->setValidator(Validator::make($data, $request->rules()));

    return $request;
}

function actAsOffboardingUser(TenantUserRole $role): User
{
    app()->instance('tenant', Tenant::factory()->create());

    return actAsOffboardingUserInCurrentTenant($role);
}

function actAsOffboardingUserInCurrentTenant(TenantUserRole $role): User
{
    $tenant = app('tenant');

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    test()->actingAs($user);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('starts offboarding without separating the employee', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

    (new EmployeeOffboardingController)->start(offboardingRequest(StartOffboardingRequest::class, [
        'separation_type' => 'resigned',
        'filed_date' => '2026-11-01',
        'last_working_date' => '2026-12-01',
        'reason' => 'Moving abroad',
    ]), $employee);

    $offboarding = $employee->refresh()->currentOffboarding();

    expect($employee->employment_status)->toBe(EmploymentStatus::Active)
        ->and($offboarding->separation_type)->toBe(EmploymentStatus::Resigned)
        ->and($offboarding->separated_at)->toBeNull()
        ->and($offboarding->final_pay_status)->toBe(OffboardingStepStatus::Pending)
        ->and($offboarding->clearanceItems()->pluck('name')->all())->toBe(EmployeeOffboarding::DEFAULT_CLEARANCE_ITEMS);
});

it('refuses to start a second offboarding', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = EmployeeOffboarding::factory()->create()->employee;

    (new EmployeeOffboardingController)->start(offboardingRequest(StartOffboardingRequest::class, [
        'separation_type' => 'resigned',
        'last_working_date' => '2026-12-01',
    ]), $employee);
})->throws(HttpException::class);

it('rejects active as a separation type', function () {
    $validator = Validator::make(
        ['separation_type' => 'active', 'last_working_date' => '2026-12-01'],
        (new StartOffboardingRequest)->rules()
    );

    expect($validator->errors()->has('separation_type'))->toBeTrue();
});

it('finalises the existing offboarding when the employee is separated', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();

    (new EmployeeController)->separate(offboardingRequest(SeparateEmployeeRequest::class, [
        'employment_status' => 'terminated',
        'termination_date' => '2026-11-20',
        'remarks' => 'Policy violation',
    ]), $offboarding->employee);

    $offboarding->refresh();

    expect(EmployeeOffboarding::count())->toBe(1)
        ->and($offboarding->separation_type)->toBe(EmploymentStatus::Terminated)
        ->and($offboarding->last_working_date->toDateString())->toBe('2026-11-20')
        ->and($offboarding->reason)->toBe('Policy violation')
        ->and($offboarding->separated_at)->not->toBeNull();
});

it('creates an offboarding record when separating directly', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

    (new EmployeeController)->separate(offboardingRequest(SeparateEmployeeRequest::class, [
        'employment_status' => 'resigned',
        'termination_date' => '2026-11-20',
    ]), $employee);

    $offboarding = $employee->refresh()->currentOffboarding();

    expect($offboarding->separated_at)->not->toBeNull()
        ->and($offboarding->clearanceItems()->count())->toBe(count(EmployeeOffboarding::DEFAULT_CLEARANCE_ITEMS));
});

it('ignores a finalised offboarding after a rehire', function () {
    $offboarding = EmployeeOffboarding::factory()->create(['separated_at' => now()]);
    $offboarding->employee->update(['employment_status' => EmploymentStatus::Active]);

    expect($offboarding->employee->currentOffboarding())->toBeNull();
});

it('updates exit interview, final pay and COE status', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();

    (new EmployeeOffboardingController)->update(offboardingRequest(UpdateOffboardingRequest::class, [
        'exit_interview_date' => '2026-11-25',
        'exit_interview_notes' => 'Left for a better offer.',
        'final_pay_status' => 'completed',
        'final_pay_amount' => '45000.50',
        'final_pay_released_date' => '2026-12-15',
        'coe_status' => 'processing',
    ], 'PUT'), $offboarding->employee);

    $offboarding->refresh();

    expect($offboarding->exit_interview_notes)->toBe('Left for a better offer.')
        ->and($offboarding->final_pay_status)->toBe(OffboardingStepStatus::Completed)
        ->and($offboarding->final_pay_amount)->toBe('45000.50')
        ->and($offboarding->coe_status)->toBe(OffboardingStepStatus::Processing);
});

it('rejects an unknown final pay status', function () {
    $validator = Validator::make(['final_pay_status' => 'paid'], (new UpdateOffboardingRequest)->rules());

    expect($validator->errors()->has('final_pay_status'))->toBeTrue();
});

it('adds, clears, unclears and removes clearance items', function () {
    $admin = actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();
    $controller = new EmployeeOffboardingController;

    $controller->storeClearanceItem(offboardingRequest(ClearanceItemRequest::class, ['name' => 'Company phone']), $offboarding->employee);
    $item = $offboarding->clearanceItems()->sole();

    $controller->updateClearanceItem(offboardingRequest(ClearanceItemRequest::class, ['is_cleared' => true, 'remarks' => 'Returned'], 'PUT'), $offboarding->employee, $item);
    expect($item->refresh()->cleared_at)->not->toBeNull()
        ->and($item->cleared_by)->toBe($admin->id)
        ->and($item->remarks)->toBe('Returned');

    $controller->updateClearanceItem(offboardingRequest(ClearanceItemRequest::class, ['is_cleared' => false], 'PUT'), $offboarding->employee, $item);
    expect($item->refresh()->cleared_at)->toBeNull()
        ->and($item->cleared_by)->toBeNull()
        ->and($item->remarks)->toBe('Returned');

    $controller->destroyClearanceItem($offboarding->employee, $item);
    expect($offboarding->clearanceItems()->count())->toBe(0);
});

it('does not touch a clearance item that belongs to another employee', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();
    $otherItem = EmployeeOffboarding::factory()->create()->clearanceItems()->create(['name' => 'Laptop']);

    (new EmployeeOffboardingController)->destroyClearanceItem($offboarding->employee, $otherItem);
})->throws(HttpException::class);

it('denies offboarding actions to users who cannot manage employees', function () {
    actAsOffboardingUser(TenantUserRole::Employee);
    $offboarding = EmployeeOffboarding::factory()->create();

    (new EmployeeOffboardingController)->update(offboardingRequest(UpdateOffboardingRequest::class, [
        'coe_status' => 'completed',
    ], 'PUT'), $offboarding->employee);
})->throws(AuthorizationException::class);

it('seeds clearance items from equipment issued during onboarding', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create();
    $checklist = OnboardingChecklist::factory()->create(['employee_id' => $employee->id]);
    OnboardingChecklistItem::factory()->create([
        'onboarding_checklist_id' => $checklist->id,
        'name' => 'Issue laptop',
        'equipment_details' => ['model' => 'ThinkPad T14', 'serial_number' => 'SN123', 'asset_tag' => null],
    ]);
    OnboardingChecklistItem::factory()->create([
        'onboarding_checklist_id' => $checklist->id,
        'name' => 'Sign contract',
        'equipment_details' => ['model' => null, 'serial_number' => ''],
    ]);

    $names = EmployeeOffboarding::startFor($employee, [
        'separation_type' => 'resigned',
        'last_working_date' => '2026-12-01',
    ])->clearanceItems()->pluck('name');

    expect($names)->toContain('Issue laptop ThinkPad T14 SN123')
        ->and($names)->toHaveCount(count(EmployeeOffboarding::DEFAULT_CLEARANCE_ITEMS) + 1);
});

it('keeps type and last working day locked once separated', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create([
        'separated_at' => now(),
        'last_working_date' => '2026-11-20',
    ]);
    $offboarding->employee->update(['employment_status' => EmploymentStatus::Resigned]);

    (new EmployeeOffboardingController)->update(offboardingRequest(UpdateOffboardingRequest::class, [
        'separation_type' => 'terminated',
        'last_working_date' => '2027-01-01',
        'coe_status' => 'completed',
    ], 'PUT'), $offboarding->employee);

    $offboarding->refresh();

    expect($offboarding->separation_type)->toBe(EmploymentStatus::Resigned)
        ->and($offboarding->last_working_date->toDateString())->toBe('2026-11-20')
        ->and($offboarding->coe_status)->toBe(OffboardingStepStatus::Completed);
});

it('cancels an offboarding in progress but not a finalised one', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $controller = new EmployeeOffboardingController;

    $inProgress = EmployeeOffboarding::factory()->create();
    $controller->destroy($inProgress->employee);
    expect(EmployeeOffboarding::find($inProgress->id))->toBeNull();

    $finalised = EmployeeOffboarding::factory()->create(['separated_at' => now()]);
    $finalised->employee->update(['employment_status' => EmploymentStatus::Resigned]);

    expect(fn () => $controller->destroy($finalised->employee))->toThrow(HttpException::class);
});

it('keeps exit interview notes out of the audit log', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();

    $offboarding->update(['exit_interview_notes' => 'Confidential feedback', 'coe_status' => 'processing']);

    expect(AuditLog::query()->get()->toJson())->not->toContain('Confidential feedback');
});

it('runs the offboarding flow over HTTP on the tenant domain', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
    $base = 'http://'.app('tenant')->slug.".kasamahr.test/employees/{$employee->id}";

    $this->post("{$base}/offboarding", ['separation_type' => 'resigned', 'last_working_date' => '2026-12-01'])
        ->assertRedirect();
    $this->post("{$base}/offboarding", ['separation_type' => 'active'])
        ->assertSessionHasErrors(['separation_type', 'last_working_date']);

    $item = $employee->currentOffboarding()->clearanceItems()->first();
    $this->put("{$base}/offboarding/clearance-items/{$item->id}", ['is_cleared' => true])->assertRedirect();
    expect($item->refresh()->cleared_at)->not->toBeNull();

    $this->put("{$base}/offboarding", ['exit_interview_notes' => 'Good exit'])->assertRedirect();

    $this->get($base)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Employees/Show')
            ->where('offboarding.separation_type', 'resigned')
            ->where('offboarding.last_working_date', '2026-12-01')
            ->where('offboarding.exit_interview_notes', 'Good exit')
            ->where('offboarding.clearance_items.0.cleared_by_user.id', auth()->id())
            ->missing('employee.offboarding'));

    $this->delete("{$base}/offboarding/clearance-items/{$item->id}")->assertRedirect();
    $this->delete("{$base}/offboarding")->assertRedirect();
    expect($employee->currentOffboarding())->toBeNull();

    $this->get($base)->assertInertia(fn ($page) => $page->where('offboarding', null));
});

function offboardingUrl(string $path): string
{
    return 'http://'.app('tenant')->slug.'.kasamahr.test'.$path;
}

it('blocks separation while clearance items are pending', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();
    $item = $offboarding->clearanceItems()->create(['name' => 'Laptop']);
    $employee = $offboarding->employee;
    $payload = ['employment_status' => 'resigned', 'termination_date' => '2026-11-20'];

    $this->post(offboardingUrl("/employees/{$employee->id}/separate"), $payload)
        ->assertSessionHasErrors('employment_status');
    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active)
        ->and($offboarding->refresh()->separated_at)->toBeNull();

    $item->update(['cleared_at' => now()]);

    $this->post(offboardingUrl("/employees/{$employee->id}/separate"), $payload)
        ->assertSessionHasNoErrors();
    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Resigned);
});

it('links a COE request to the offboarding and follows its status', function () {
    Notification::fake();
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
    $older = DocumentRequest::factory()->create(['employee_id' => $employee->id, 'document_type' => DocumentRequestType::Coe]);
    $offboarding = EmployeeOffboarding::factory()->create(['employee_id' => $employee->id]);

    $older->fresh()->update(['status' => DocumentRequestStatus::Collected]);
    expect($offboarding->refresh()->coe_status)->toBe(OffboardingStepStatus::Pending);

    $base = offboardingUrl("/employees/{$employee->id}/offboarding");
    $this->post("{$base}/coe-request")->assertRedirect();

    $request = DocumentRequest::query()->latest('id')->first();
    expect($offboarding->refresh()->coe_document_request_id)->toBe($request->id)
        ->and($request->id)->not->toBe($older->id);

    $this->post("{$base}/coe-request")->assertStatus(409);

    $this->put($base, ['coe_status' => 'completed', 'coe_issued_date' => '2026-01-01'])->assertRedirect();
    expect($offboarding->refresh()->coe_status)->toBe(OffboardingStepStatus::Pending);

    $this->put("{$base}/coe-request", ['status' => 'ready'])->assertRedirect();
    expect($offboarding->refresh()->coe_status)->toBe(OffboardingStepStatus::Completed)
        ->and($offboarding->coe_issued_date)->not->toBeNull()
        ->and($request->refresh()->status)->toBe(DocumentRequestStatus::Ready);

    $request->update(['status' => DocumentRequestStatus::Processing]);
    expect($offboarding->refresh()->coe_status)->toBe(OffboardingStepStatus::Processing)
        ->and($offboarding->coe_issued_date)->toBeNull();
});

it('links a COE request the employee files during offboarding, but not other document types', function () {
    $offboarding = EmployeeOffboarding::factory()->create();

    DocumentRequest::factory()->create(['employee_id' => $offboarding->employee_id, 'document_type' => DocumentRequestType::PayslipCopy]);
    expect($offboarding->refresh()->coe_document_request_id)->toBeNull();

    $coe = DocumentRequest::factory()->create([
        'employee_id' => $offboarding->employee_id,
        'document_type' => DocumentRequestType::Coe,
        'status' => DocumentRequestStatus::Processing,
    ]);
    expect($offboarding->refresh()->coe_document_request_id)->toBe($coe->id)
        ->and($offboarding->coe_status)->toBe(OffboardingStepStatus::Processing);
});

it('lets an employee file their own resignation and notifies HR', function () {
    Notification::fake();
    $admin = actAsOffboardingUser(TenantUserRole::Admin);
    $user = actAsOffboardingUserInCurrentTenant(TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'employment_status' => EmploymentStatus::Active]);
    $other = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
    $lastDay = now()->addDays(30)->toDateString();

    $this->post(offboardingUrl('/my/resignation'), [
        'last_working_date' => $lastDay,
        'reason' => 'New opportunity',
        'separation_type' => 'terminated',
        'employee_id' => $other->id,
    ])->assertSessionHasNoErrors();

    $offboarding = $employee->currentOffboarding();

    expect($offboarding->separation_type)->toBe(EmploymentStatus::Resigned)
        ->and($offboarding->filed_date->toDateString())->toBe(now()->toDateString())
        ->and($offboarding->last_working_date->toDateString())->toBe($lastDay)
        ->and($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active)
        ->and($other->currentOffboarding())->toBeNull();

    Notification::assertSentTo($admin, OffboardingNotification::class, fn ($notification) => $notification->url === "/employees/{$employee->id}?tab=offboarding");
    Notification::assertNotSentTo($user, OffboardingNotification::class);

    $this->post(offboardingUrl('/my/resignation'), ['last_working_date' => $lastDay])
        ->assertSessionHasErrors('last_working_date');
    expect(EmployeeOffboarding::count())->toBe(1);

    $offboarding->update(['exit_interview_notes' => 'Private HR notes']);
    $offboarding->clearanceItems()->first()->update(['remarks' => 'HR-only remark']);

    $this->get(offboardingUrl('/my/resignation'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('My/Resignation/Index')
            ->where('offboarding.separation_type_label', 'Resigned')
            ->missing('offboarding.exit_interview_notes')
            ->missing('offboarding.clearance_items.0.remarks'))
        ->assertDontSee('Private HR notes')
        ->assertDontSee('HR-only remark');
});

it('rejects a resignation with a past last working day', function () {
    $user = actAsOffboardingUser(TenantUserRole::Employee);
    Employee::factory()->create(['user_id' => $user->id, 'employment_status' => EmploymentStatus::Active]);

    $this->post(offboardingUrl('/my/resignation'), ['last_working_date' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors('last_working_date');

    expect(EmployeeOffboarding::count())->toBe(0);
});

it('hides a pending termination from the employee', function () {
    Notification::fake();
    actAsOffboardingUser(TenantUserRole::Admin);
    $user = User::factory()->create();
    $user->tenants()->attach(app('tenant')->id, ['role' => TenantUserRole::Employee->value, 'invited_at' => now(), 'invitation_accepted_at' => now()]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'employment_status' => EmploymentStatus::Active]);
    $base = offboardingUrl("/employees/{$employee->id}/offboarding");

    $this->post($base, ['separation_type' => 'terminated', 'last_working_date' => '2026-12-01'])->assertRedirect();
    $this->put($base, ['final_pay_status' => 'completed'])->assertRedirect();
    $this->post("{$base}/coe-request")->assertStatus(409);
    DocumentRequest::factory()->create(['employee_id' => $employee->id, 'document_type' => DocumentRequestType::Coe]);

    Notification::assertNothingSentTo($user);
    expect($employee->currentOffboarding()->coe_document_request_id)->toBeNull();

    $this->actingAs($user);
    $this->get(offboardingUrl('/my/resignation'))->assertInertia(fn ($page) => $page->where('offboarding', null));
    $this->post(offboardingUrl('/my/resignation'), ['last_working_date' => now()->addDays(30)->toDateString()])
        ->assertSessionHasErrors(['last_working_date' => 'Your resignation could not be filed online. Please contact HR.']);
});

it('notifies a resigning employee about offboarding start, clearance and final pay', function () {
    Notification::fake();
    actAsOffboardingUser(TenantUserRole::Admin);
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'employment_status' => EmploymentStatus::Active]);
    $base = offboardingUrl("/employees/{$employee->id}/offboarding");

    $this->post($base, ['separation_type' => 'resigned', 'last_working_date' => '2026-12-01'])->assertRedirect();
    Notification::assertSentToTimes($user, OffboardingNotification::class, 1);

    $items = $employee->currentOffboarding()->clearanceItems;
    foreach ($items as $item) {
        $this->put("{$base}/clearance-items/{$item->id}", ['is_cleared' => true]);
    }
    Notification::assertSentToTimes($user, OffboardingNotification::class, 2);

    $this->put($base, ['final_pay_status' => 'completed']);
    $this->put($base, ['final_pay_status' => 'completed', 'reason' => 'Edited']);
    Notification::assertSentToTimes($user, OffboardingNotification::class, 3);
});

it('lists offboardings for HR only', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $inProgress = EmployeeOffboarding::factory()->create(['exit_interview_notes' => 'Private HR notes']);
    $inProgress->clearanceItems()->createMany([['name' => 'Laptop', 'cleared_at' => now()], ['name' => 'ID']]);
    EmployeeOffboarding::factory()->create(['separated_at' => now()]);

    $this->get(offboardingUrl('/offboarding'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Offboarding/Index')
            ->has('offboardings.data', 2)
            ->where('summary.in_progress', 1)
            ->where('summary.separated', 1))
        ->assertDontSee('Private HR notes');

    $this->get(offboardingUrl('/offboarding?status=in_progress'))
        ->assertInertia(fn ($page) => $page
            ->has('offboardings.data', 1)
            ->where('offboardings.data.0.id', $inProgress->id)
            ->where('offboardings.data.0.clearance_items_count', 2)
            ->where('offboardings.data.0.cleared_items_count', 1));

    actAsOffboardingUserInCurrentTenant(TenantUserRole::Employee);
    $this->get(offboardingUrl('/offboarding'))->assertForbidden();
});

it('applies the clearance block and finalisation to status edits outside the Separate button', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $offboarding = EmployeeOffboarding::factory()->create();
    $item = $offboarding->clearanceItems()->create(['name' => 'Laptop']);
    $employee = $offboarding->employee;

    expect(fn () => $employee->separateIfLeaving(['employment_status' => 'resigned']))
        ->toThrow(ValidationException::class);
    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active);

    $this->putJson(offboardingUrl("/api/employees/{$employee->id}"), [
        'employee_number' => $employee->employee_number,
        'first_name' => $employee->first_name,
        'last_name' => $employee->last_name,
        'email' => $employee->email,
        'hire_date' => $employee->hire_date->toDateString(),
        'employment_status' => 'resigned',
    ])->assertJsonValidationErrors('employment_status');
    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active);

    $item->update(['cleared_at' => now()]);
    $employee->separateIfLeaving(['employment_status' => 'resigned']);

    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Resigned)
        ->and($employee->termination_date)->not->toBeNull()
        ->and($offboarding->refresh()->separated_at)->not->toBeNull();

    $employee->separateIfLeaving(['employment_status' => 'terminated']);
    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Resigned);
});

it('does not notify an already separated employee when HR backfills a record', function () {
    Notification::fake();
    actAsOffboardingUser(TenantUserRole::Admin);
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'employment_status' => EmploymentStatus::Resigned]);

    $this->post(offboardingUrl("/employees/{$employee->id}/offboarding"), ['separation_type' => 'resigned', 'last_working_date' => '2026-10-01'])
        ->assertRedirect();

    expect($employee->currentOffboarding()->separated_at)->not->toBeNull();
    Notification::assertNothingSentTo($user);
});

it('lets an HR manager use the list and COE flow and receive resignation notices', function () {
    $manager = actAsOffboardingUser(TenantUserRole::HrManager);
    actAsOffboardingUserInCurrentTenant(TenantUserRole::HrStaff);
    $this->actingAs($manager);
    $offboarding = EmployeeOffboarding::factory()->create();
    $base = offboardingUrl("/employees/{$offboarding->employee_id}/offboarding");

    $this->get(offboardingUrl('/offboarding'))->assertOk();
    $this->post("{$base}/coe-request")->assertRedirect();
    $this->put("{$base}/coe-request", ['status' => 'collected'])->assertRedirect();

    expect($offboarding->refresh()->coe_status)->toBe(OffboardingStepStatus::Completed)
        ->and(OffboardingNotification::hrRecipients()->modelKeys())->toBe([$manager->id]);
});

it('shares the success message with the page so it can be shown as a toast', function () {
    actAsOffboardingUser(TenantUserRole::Admin);
    $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
    $base = offboardingUrl("/employees/{$employee->id}");

    $this->from($base)
        ->followingRedirects()
        ->post("{$base}/offboarding", ['separation_type' => 'resigned', 'last_working_date' => '2026-12-01'])
        ->assertInertia(fn ($page) => $page->where('flash.success', 'Offboarding started.'));

    $this->get($base)->assertInertia(fn ($page) => $page->where('flash.success', null));
});
