<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\TrialExpiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

describe('TrialExpiredNotification', function () {
    it('sends via mail and database channels', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $notification = new TrialExpiredNotification($tenant);
        $user = User::factory()->create();

        expect($notification->via($user))->toBe(['mail', 'database']);
    });

    it('sends mail with correct subject', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new TrialExpiredNotification($tenant);
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Your trial has expired');
    });

    it('returns correct array representation', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new TrialExpiredNotification($tenant);
        $data = $notification->toArray($user);

        expect($data['type'])->toBe('trial_expired');
        expect($data['tenant_id'])->toBe($tenant->id);
        expect($data['message'])->toContain($tenant->name);
    });

    it('delivers notification to user', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $user->notify(new TrialExpiredNotification($tenant));

        Notification::assertSentTo($user, TrialExpiredNotification::class);
    });
});

describe('PaymentFailedNotification', function () {
    it('sends via mail and database channels', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $notification = new PaymentFailedNotification($tenant, 'Card declined');
        $user = User::factory()->create();

        expect($notification->via($user))->toBe(['mail', 'database']);
    });

    it('sends mail with reason when provided', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new PaymentFailedNotification($tenant, 'Card declined');
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Payment failed for your subscription');
    });

    it('returns correct array representation', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new PaymentFailedNotification($tenant, 'Insufficient funds');
        $data = $notification->toArray($user);

        expect($data['type'])->toBe('payment_failed');
        expect($data['tenant_id'])->toBe($tenant->id);
        expect($data['reason'])->toBe('Insufficient funds');
    });

    it('delivers notification to user', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $user->notify(new PaymentFailedNotification($tenant));

        Notification::assertSentTo($user, PaymentFailedNotification::class);
    });
});

describe('SubscriptionCancelledNotification', function () {
    it('sends via mail and database channels', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $notification = new SubscriptionCancelledNotification($tenant);
        $user = User::factory()->create();

        expect($notification->via($user))->toBe(['mail', 'database']);
    });

    it('sends mail with ends_at when provided', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new SubscriptionCancelledNotification($tenant, 'March 1, 2026');
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Your subscription has been cancelled');
    });

    it('returns correct array representation', function () {
        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $notification = new SubscriptionCancelledNotification($tenant, '2026-03-01');
        $data = $notification->toArray($user);

        expect($data['type'])->toBe('subscription_cancelled');
        expect($data['tenant_id'])->toBe($tenant->id);
        expect($data['ends_at'])->toBe('2026-03-01');
    });

    it('delivers notification to user', function () {
        Notification::fake();

        $plan = Plan::factory()->starter()->create();
        $tenant = Tenant::factory()->withPlan($plan)->create();
        $user = User::factory()->create();

        $user->notify(new SubscriptionCancelledNotification($tenant));

        Notification::assertSentTo($user, SubscriptionCancelledNotification::class);
    });
});
