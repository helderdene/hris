<?php

use App\Enums\TenantProvisioningStatus;
use App\Jobs\ProvisionTenantJob;
use App\Models\HelpCategory;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantRedirectToken;
use App\Models\User;
use App\Services\Billing\PayMongoCustomerService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Corporation',
        'slug' => 'acme',
        'business_info' => ['company_name' => 'Acme Corporation Inc.'],
    ], $overrides);
}

it('registers the tenant and queues provisioning instead of running it in the request', function () {
    Queue::fake();
    $plan = Plan::factory()->professional()->create();
    $user = User::factory()->withoutTwoFactor()->create();

    mock(TenantDatabaseManager::class)->shouldReceive('createSchema')->once();

    $response = $this->actingAs($user)->post('/register-organization', registrationPayload());

    $tenant = Tenant::where('slug', 'acme')->firstOrFail();

    $response->assertRedirect(route('tenant.register', ['provisioning' => 'acme']));
    expect($tenant->plan_id)->toBe($plan->id)
        ->and($tenant->provisioning_status)->toBe(TenantProvisioningStatus::Pending)
        ->and($tenant->provisioning_user_id)->toBe($user->id)
        ->and($tenant->onTrial())->toBeTrue()
        ->and($tenant->users()->count())->toBe(0);

    Queue::assertPushed(
        ProvisionTenantJob::class,
        fn (ProvisionTenantJob $job) => $job->tenantId === $tenant->id && $job->adminUserId === $user->id
    );
});

it('does not queue provisioning when validation fails', function () {
    Queue::fake();
    Tenant::factory()->create(['slug' => 'acme']);
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->post('/register-organization', registrationPayload())
        ->assertSessionHasErrors('slug');

    Queue::assertNothingPushed();
});

it('provisions the schema and attaches the admin last', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $tenant = Tenant::factory()->create(['provisioning_status' => TenantProvisioningStatus::Pending]);
    $user = User::factory()->create();

    mock(TenantDatabaseManager::class, function ($mock) {
        $mock->shouldReceive('createSchema')->twice();
        $mock->shouldReceive('migrateSchema')->twice();
    });
    mock(PayMongoCustomerService::class)
        ->shouldReceive('createOrGet')
        ->andThrow(new RuntimeException('PayMongo is down'));

    ProvisionTenantJob::dispatchSync($tenant->id, $user->id);
    ProvisionTenantJob::dispatchSync($tenant->id, $user->id);

    expect($tenant->users()->count())->toBe(1)
        ->and($tenant->users()->wherePivot('role', 'admin')->count())->toBe(1)
        ->and(HelpCategory::count())->toBeGreaterThan(0)
        ->and($tenant->fresh()->provisioning_status)->toBeNull();
});

it('keeps the page waiting while the tenant is still provisioning', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs($user)
        ->get('/register-organization?provisioning=acme')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('TenantRegistration')
            ->where('provisioningSlug', 'acme')
            ->where('provisionedRedirectUrl', null));

    expect(TenantRedirectToken::count())->toBe(0);
});

it('hands over a subdomain redirect url once the user is attached', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $tenant->users()->attach($user->id, ['role' => 'admin']);

    $this->actingAs($user)
        ->get('/register-organization?provisioning=acme')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('provisionedRedirectUrl', function (string $url) use ($user, $tenant) {
                $token = TenantRedirectToken::where('user_id', $user->id)
                    ->where('tenant_id', $tenant->id)
                    ->firstOrFail();

                return str_contains($url, '://acme.') && str_contains($url, "token={$token->token}");
            }));
});

it('shows the plain form when nothing is provisioning', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get('/register-organization')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('provisioningSlug', null)
            ->where('provisionedRedirectUrl', null));
});

it('marks the tenant failed when the job fails, without touching a finished tenant', function () {
    $pending = Tenant::factory()->create(['provisioning_status' => TenantProvisioningStatus::Pending]);
    $finished = Tenant::factory()->create();

    (new ProvisionTenantJob($pending->id, 1))->failed(new RuntimeException('boom'));
    (new ProvisionTenantJob($finished->id, 1))->failed(new RuntimeException('boom'));

    expect($pending->fresh()->provisioning_status)->toBe(TenantProvisioningStatus::Failed)
        ->and($finished->fresh()->provisioning_status)->toBeNull();
});

it('sends a returning user back to their organization that is still provisioning', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    Tenant::factory()->create([
        'slug' => 'acme',
        'provisioning_status' => TenantProvisioningStatus::Pending,
        'provisioning_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/register-organization')
        ->assertRedirect(route('tenant.register', ['provisioning' => 'acme']));

    $this->actingAs($user)
        ->get('/register-organization?provisioning=acme')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('provisioningSlug', 'acme')
            ->where('provisioningFailed', false)
            ->where('provisionedRedirectUrl', null));
});

it('does not show another user\'s unfinished organization', function () {
    $owner = User::factory()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    Tenant::factory()->create([
        'provisioning_status' => TenantProvisioningStatus::Failed,
        'provisioning_user_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get('/register-organization')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('provisioningSlug', null)
            ->where('provisioningFailed', false));
});

it('lets the owner retry a failed organization', function () {
    Queue::fake();
    $user = User::factory()->withoutTwoFactor()->create();
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'provisioning_status' => TenantProvisioningStatus::Failed,
        'provisioning_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/register-organization?provisioning=acme')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('provisioningFailed', true));

    $this->actingAs($user)->post('/register-organization/retry')->assertRedirect(route('tenant.register'));

    expect($tenant->fresh()->provisioning_status)->toBe(TenantProvisioningStatus::Pending);
    Queue::assertPushed(ProvisionTenantJob::class, fn (ProvisionTenantJob $job) => $job->tenantId === $tenant->id);
});

it('does not queue a retry for a pending organization or for someone else', function () {
    Queue::fake();
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    $failed = Tenant::factory()->create([
        'provisioning_status' => TenantProvisioningStatus::Failed,
        'provisioning_user_id' => $owner->id,
    ]);
    Tenant::factory()->create([
        'provisioning_status' => TenantProvisioningStatus::Pending,
        'provisioning_user_id' => $other->id,
    ]);

    $this->actingAs($other)->post('/register-organization/retry');

    expect($failed->fresh()->provisioning_status)->toBe(TenantProvisioningStatus::Failed);
    Queue::assertNothingPushed();
});

it('excludes unfinished tenants from the provisioned scope used by scheduled work', function () {
    $ready = Tenant::factory()->create();
    Tenant::factory()->create(['provisioning_status' => TenantProvisioningStatus::Pending]);
    Tenant::factory()->create(['provisioning_status' => TenantProvisioningStatus::Failed]);

    expect(Tenant::provisioned()->pluck('id')->all())->toBe([$ready->id]);
});
