<?php

use App\Actions\CreateNewHireUserAction;
use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\TenantUserController;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\NewHireAccountSetup;
use App\Services\Recruitment\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-users', fn () => true);
    Gate::define('can-manage-organization', fn () => true);
});

describe('CreateNewHireUserAction', function () {
    it('creates a new user with null password', function () {
        $action = new CreateNewHireUserAction;

        $result = $action->execute(
            email: 'newhire@example.com',
            name: 'New Hire',
            tenant: $this->tenant
        );

        expect($result['user']->email)->toBe('newhire@example.com');
        expect($result['user']->name)->toBe('New Hire');
        expect($result['user']->password)->toBeNull();
        expect($result['is_new'])->toBeTrue();
        expect($result['token'])->toHaveLength(64);
    });

    it('links the user to the tenant with employee role', function () {
        $action = new CreateNewHireUserAction;

        $result = $action->execute(
            email: 'newhire@example.com',
            name: 'New Hire',
            tenant: $this->tenant
        );

        $tenantUser = TenantUser::where('user_id', $result['user']->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        expect($tenantUser)->not->toBeNull();
        expect($tenantUser->role)->toBe(TenantUserRole::Employee);
        expect($tenantUser->invitation_token)->toBe($result['token']);
        expect($tenantUser->invitation_expires_at)->not->toBeNull();
    });

    it('reuses existing user if email already exists', function () {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $action = new CreateNewHireUserAction;

        $result = $action->execute(
            email: 'existing@example.com',
            name: 'Updated Name',
            tenant: $this->tenant
        );

        expect($result['user']->id)->toBe($existingUser->id);
        expect($result['is_new'])->toBeFalse();
        expect($result['user']->name)->toBe('Updated Name');
    });

    it('sets invitation expiry to approximately 30 days', function () {
        $action = new CreateNewHireUserAction;

        $result = $action->execute(
            email: 'newhire@example.com',
            name: 'New Hire',
            tenant: $this->tenant
        );

        $tenantUser = TenantUser::where('user_id', $result['user']->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        // Check that expiry is between 29 and 31 days from now
        $daysUntilExpiry = now()->diffInDays($tenantUser->invitation_expires_at, false);
        expect($daysUntilExpiry)->toBeGreaterThanOrEqual(29);
        expect($daysUntilExpiry)->toBeLessThanOrEqual(31);
    });
});

describe('User creation on offer acceptance', function () {
    it('creates user when offer is accepted', function () {
        Storage::fake('local');
        Notification::fake();

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
        $candidateEmail = $application->candidate->email;

        $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
            'job_application_id' => $application->id,
            'created_by' => $this->user->id,
        ]);

        $service = app(OfferService::class);
        $service->acceptOffer($offer, [
            'signer_name' => 'Jane Doe',
            'signer_email' => 'jane@example.com',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
        ]);

        // User should be created
        $newUser = User::where('email', $candidateEmail)->first();
        expect($newUser)->not->toBeNull();
        expect($newUser->password)->toBeNull();

        // User should be linked to tenant
        $tenantUser = TenantUser::where('user_id', $newUser->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();
        expect($tenantUser)->not->toBeNull();
        expect($tenantUser->role)->toBe(TenantUserRole::Employee);
    });

    it('sends account setup email automatically on offer acceptance', function () {
        Storage::fake('local');
        Notification::fake();

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Sent)->create([
            'job_application_id' => $application->id,
            'created_by' => $this->user->id,
        ]);

        $service = app(OfferService::class);
        $service->acceptOffer($offer, [
            'signer_name' => 'Jane Doe',
            'signer_email' => 'jane@example.com',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        ]);

        Notification::assertSentTo(
            User::where('email', $application->candidate->email)->first(),
            NewHireAccountSetup::class
        );
    });
});

describe('Manual account setup email', function () {
    it('allows HR to send account setup email', function () {
        Notification::fake();

        $newHire = User::factory()->create(['password' => null]);
        $newHire->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_token' => 'test-token-123',
            'invitation_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->user);

        $controller = new TenantUserController;
        $response = $controller->sendAccountSetupEmail($newHire);

        expect($response->getData()->message)->toBe('Account setup email sent successfully.');

        Notification::assertSentTo($newHire, NewHireAccountSetup::class);
    });

    it('generates new token if expired', function () {
        Notification::fake();

        $newHire = User::factory()->create(['password' => null]);
        $newHire->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now()->subDays(40),
            'invitation_token' => 'old-expired-token',
            'invitation_expires_at' => now()->subDays(10), // Expired
        ]);

        $this->actingAs($this->user);

        $controller = new TenantUserController;
        $controller->sendAccountSetupEmail($newHire);

        $tenantUser = TenantUser::where('user_id', $newHire->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        expect($tenantUser->invitation_token)->not->toBe('old-expired-token');
        expect($tenantUser->invitation_expires_at->isFuture())->toBeTrue();
    });

    it('prevents sending to users who already set up their account', function () {
        $existingUser = User::factory()->create(['password' => 'hashed-password']);
        $existingUser->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now()->subDays(30),
            'invitation_accepted_at' => now()->subDays(25),
        ]);

        $this->actingAs($this->user);

        $controller = new TenantUserController;

        expect(fn () => $controller->sendAccountSetupEmail($existingUser))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('returns 404 for users not in this tenant', function () {
        $otherUser = User::factory()->create();

        $this->actingAs($this->user);

        $controller = new TenantUserController;

        expect(fn () => $controller->sendAccountSetupEmail($otherUser))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
    });

    it('requires user management permission', function () {
        Gate::define('can-manage-users', fn () => false);

        $newHire = User::factory()->create(['password' => null]);
        $newHire->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::Employee->value,
            'invited_at' => now(),
            'invitation_token' => 'test-token',
            'invitation_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($this->user);

        $controller = new TenantUserController;

        expect(fn () => $controller->sendAccountSetupEmail($newHire))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    });
});
