<?php

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

    $response = $this->actingAs($user)->post('/register-organization', registrationPayload());

    $tenant = Tenant::where('slug', 'acme')->firstOrFail();

    $response->assertRedirect(route('tenant.register', ['provisioning' => 'acme']));
    expect($tenant->plan_id)->toBe($plan->id)
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

    $tenant = Tenant::factory()->create();
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
        ->and(HelpCategory::count())->toBeGreaterThan(0);
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
