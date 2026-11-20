<?php

use App\Actions\InviteUserAction;
use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function autoLinkMember(array $userAttributes = [], array $pivot = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach(app('tenant')->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
        ...$pivot,
    ]);

    return $user;
}

function autoLinkInvite(string $email, ?int $employeeId = null): User
{
    return app(InviteUserAction::class)->execute(
        $email,
        'Invited Person',
        TenantUserRole::Employee,
        app('tenant')->id,
        test()->inviter->id,
        $employeeId,
    );
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Notification::fake();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    app()->instance('tenant', Tenant::factory()->create());
    $this->inviter = autoLinkMember();
});

it('links an invited user to the unlinked employee with the same email', function () {
    $employee = Employee::factory()->create(['email' => 'Maria@Example.com', 'user_id' => null]);

    $user = autoLinkInvite('maria@example.com');

    expect($employee->refresh()->user_id)->toBe($user->id);
});

it('does not link on invite when no employee has that email or the employee already has a login', function () {
    $other = autoLinkMember();
    $taken = Employee::factory()->create(['email' => 'taken@example.com', 'user_id' => $other->id]);
    $unrelated = Employee::factory()->create(['email' => 'someone@example.com', 'user_id' => null]);

    autoLinkInvite('taken@example.com');
    autoLinkInvite('nobody@example.com');

    expect($taken->refresh()->user_id)->toBe($other->id)
        ->and($unrelated->refresh()->user_id)->toBeNull();
});

it('keeps the employee HR picked even when another employee has the invited email', function () {
    $picked = Employee::factory()->create(['email' => 'picked@example.com', 'user_id' => null]);
    $sameEmail = Employee::factory()->create(['email' => 'maria@example.com', 'user_id' => null]);

    $user = autoLinkInvite('maria@example.com', $picked->id);

    expect($picked->refresh()->user_id)->toBe($user->id)
        ->and($sameEmail->refresh()->user_id)->toBeNull();
});

it('links a new employee to the tenant member with the same email', function () {
    $user = autoLinkMember(['email' => 'shane@example.com']);

    $employee = Employee::factory()->create(['email' => 'SHANE@example.com', 'user_id' => null]);

    expect($employee->user_id)->toBe($user->id);
});

it('links when an existing employee email is changed to a member email, but not on unrelated edits', function () {
    $employee = Employee::factory()->create(['email' => 'old@example.com', 'user_id' => null]);
    $user = autoLinkMember(['email' => 'shane@example.com']);

    $employee->update(['first_name' => 'Renamed']);
    expect($employee->refresh()->user_id)->toBeNull();

    $employee->update(['email' => 'shane@example.com']);
    expect($employee->refresh()->user_id)->toBe($user->id);
});

it('links a new employee to a user whose invitation is still pending', function () {
    $user = autoLinkMember(['email' => 'pending@example.com', 'email_verified_at' => null, 'password' => null], ['invitation_accepted_at' => null]);

    expect(Employee::factory()->create(['email' => 'pending@example.com', 'user_id' => null])->user_id)->toBe($user->id);
});

it('never links a new employee to the wrong account', function (Closure $setup) {
    $setup();

    expect(Employee::factory()->create(['email' => 'target@example.com', 'user_id' => null])->user_id)->toBeNull();
})->with([
    'user in another tenant only' => [fn () => User::factory()->create(['email' => 'target@example.com'])
        ->tenants()->attach(Tenant::factory()->create()->id, ['role' => 'employee', 'invited_at' => now(), 'invitation_accepted_at' => now()])],
    'member who set an unverified email themselves' => [fn () => autoLinkMember(['email' => 'target@example.com', 'email_verified_at' => null])],
    'member already linked to another employee' => [fn () => Employee::factory()->create([
        'email' => 'first@example.com',
        'user_id' => autoLinkMember(['email' => 'target@example.com'])->id,
    ])],
]);

it('does not replace an existing link when the employee email changes', function () {
    $current = autoLinkMember();
    autoLinkMember(['email' => 'new@example.com']);
    $employee = Employee::factory()->create(['email' => 'old@example.com', 'user_id' => $current->id]);

    $employee->update(['email' => 'new@example.com']);

    expect($employee->refresh()->user_id)->toBe($current->id);
});

it('does not link an account that claimed the email without verifying it, even when re-invited', function () {
    $claimed = autoLinkMember(['email' => 'victim@example.com', 'email_verified_at' => null]);
    $employee = Employee::factory()->create(['email' => 'victim@example.com', 'user_id' => null]);

    expect($employee->user_id)->toBeNull();

    autoLinkInvite('victim@example.com');
    expect($employee->refresh()->user_id)->toBeNull();

    $later = Employee::factory()->create(['email' => 'other@example.com', 'user_id' => null]);
    $later->update(['email' => 'victim2@example.com']);
    $claimed->forceFill(['email' => 'victim3@example.com'])->save();
    autoLinkInvite('victim3@example.com');

    expect(Employee::factory()->create(['email' => 'victim3@example.com', 'user_id' => null])->user_id)->toBeNull();
});
