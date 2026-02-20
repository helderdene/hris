<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\NotificationController;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BulkPayslipReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForNotifications(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForNotifications(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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
 * Helper to create a notification for a user.
 */
function createNotificationForUser(User $user, array $data = [], bool $read = false): DatabaseNotification
{
    $notification = $user->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => BulkPayslipReady::class,
        'data' => array_merge([
            'type' => 'bulk_payslip_ready',
            'title' => 'Bulk Payslips Ready',
            'message' => 'Your bulk payslip download is ready.',
        ], $data),
        'read_at' => $read ? now() : null,
    ]);

    return $notification;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

describe('Notification API', function () {
    it('returns notifications for authenticated user', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);

        // Create some notifications
        createNotificationForUser($user, ['title' => 'Notification 1']);
        createNotificationForUser($user, ['title' => 'Notification 2'], read: true);
        createNotificationForUser($user, ['title' => 'Notification 3']);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create('/api/notifications', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->index($request);
        $data = json_decode($response->getContent(), true);

        expect($data)->toHaveKeys(['notifications', 'unread_count']);
        expect($data['notifications'])->toHaveCount(3);
        expect($data['unread_count'])->toBe(2);
    });

    it('returns empty list when user has no notifications', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create('/api/notifications', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->index($request);
        $data = json_decode($response->getContent(), true);

        expect($data['notifications'])->toHaveCount(0);
        expect($data['unread_count'])->toBe(0);
    });

    it('marks a notification as read', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);
        $notification = createNotificationForUser($user);

        expect($notification->read_at)->toBeNull();

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create("/api/notifications/{$notification->id}/read", 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $controller->markAsRead($request, $notification);
        $data = json_decode($response->getContent(), true);

        expect($data['message'])->toBe('Notification marked as read.');

        $notification->refresh();
        expect($notification->read_at)->not->toBeNull();
    });

    it('prevents user from marking another user notification as read', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user1 = createTenantUserForNotifications($tenant, TenantUserRole::Admin);
        $user2 = createTenantUserForNotifications($tenant, TenantUserRole::Employee);
        $notification = createNotificationForUser($user1);

        $this->actingAs($user2);

        $controller = new NotificationController;
        $request = Request::create("/api/notifications/{$notification->id}/read", 'POST');
        $request->setUserResolver(fn () => $user2);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $controller->markAsRead($request, $notification);
    });

    it('marks all notifications as read', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);

        // Create multiple unread notifications
        createNotificationForUser($user, ['title' => 'Notification 1']);
        createNotificationForUser($user, ['title' => 'Notification 2']);
        createNotificationForUser($user, ['title' => 'Notification 3']);

        expect($user->unreadNotifications()->count())->toBe(3);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create('/api/notifications/mark-all-read', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $controller->markAllAsRead($request);
        $data = json_decode($response->getContent(), true);

        expect($data['message'])->toBe('All notifications marked as read.');

        // Refresh the user's notifications
        $user->refresh();
        expect($user->unreadNotifications()->count())->toBe(0);
    });

    it('downloads file from notification', function () {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);

        // Create a test file
        $filePath = 'payslips/bulk/test-payslips.pdf';
        Storage::disk('local')->put($filePath, 'test pdf content');

        $notification = createNotificationForUser($user, [
            'file_path' => $filePath,
            'file_name' => 'Payslips-2024-01.pdf',
        ]);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create("/api/notifications/{$notification->id}/download", 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->download($request, $notification);

        expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

        // Notification should be marked as read after download
        $notification->refresh();
        expect($notification->read_at)->not->toBeNull();
    });

    it('returns 404 when notification has no file', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);
        $notification = createNotificationForUser($user);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create("/api/notifications/{$notification->id}/download", 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $controller->download($request, $notification);
    });

    it('prevents downloading from another user notification', function () {
        Storage::fake('local');

        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user1 = createTenantUserForNotifications($tenant, TenantUserRole::Admin);
        $user2 = createTenantUserForNotifications($tenant, TenantUserRole::Employee);

        $filePath = 'payslips/bulk/test-payslips.pdf';
        Storage::disk('local')->put($filePath, 'test pdf content');

        $notification = createNotificationForUser($user1, [
            'file_path' => $filePath,
            'file_name' => 'Payslips.pdf',
        ]);

        $this->actingAs($user2);

        $controller = new NotificationController;
        $request = Request::create("/api/notifications/{$notification->id}/download", 'GET');
        $request->setUserResolver(fn () => $user2);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $controller->download($request, $notification);
    });

    it('notification resource transforms data correctly', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForNotifications($tenant);

        $user = createTenantUserForNotifications($tenant, TenantUserRole::Admin);
        $notification = createNotificationForUser($user, [
            'title' => 'Test Title',
            'message' => 'Test message',
            'file_path' => '/path/to/file.pdf',
            'file_name' => 'file.pdf',
        ]);

        $this->actingAs($user);

        $controller = new NotificationController;
        $request = Request::create('/api/notifications', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $controller->index($request);
        $data = json_decode($response->getContent(), true);

        $firstNotification = $data['notifications'][0];

        expect($firstNotification)->toHaveKeys([
            'id',
            'type',
            'title',
            'message',
            'is_read',
            'read_at',
            'created_at',
            'time_ago',
            'file_path',
            'file_name',
        ]);
        expect($firstNotification['title'])->toBe('Test Title');
        expect($firstNotification['message'])->toBe('Test message');
        expect($firstNotification['is_read'])->toBeFalse();
        expect($firstNotification['file_path'])->toBe('/path/to/file.pdf');
        expect($firstNotification['file_name'])->toBe('file.pdf');
    });
});
