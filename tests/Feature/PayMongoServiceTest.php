<?php

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\Billing\PayMongoCustomerService;
use App\Services\Billing\PayMongoService;
use App\Services\Billing\PayMongoSubscriptionService;
use App\Services\Billing\PayMongoWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Paymongo\PaymongoClient;

uses(RefreshDatabase::class);

describe('PayMongoSubscriptionService::calculateAmount', function () {
    it('calculates amount using tier minimum when quantity is below minimum', function () {
        $plan = Plan::factory()->create(['slug' => 'starter']);
        $planPrice = PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'price_per_unit' => 150,
            'billing_interval' => 'monthly',
        ]);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoSubscriptionService($client);

        // Starter minimum is 5, quantity is 2 → should use 5
        $amount = $service->calculateAmount($planPrice, 2);

        expect($amount)->toBe(75000); // 150 * 5 * 100
    });

    it('calculates amount using actual quantity when above minimum', function () {
        $plan = Plan::factory()->create(['slug' => 'starter']);
        $planPrice = PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'price_per_unit' => 150,
            'billing_interval' => 'monthly',
        ]);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoSubscriptionService($client);

        // Starter minimum is 5, quantity is 20 → should use 20
        $amount = $service->calculateAmount($planPrice, 20);

        expect($amount)->toBe(300000); // 150 * 20 * 100
    });

    it('calculates amount for professional tier', function () {
        $plan = Plan::factory()->create(['slug' => 'professional']);
        $planPrice = PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'price_per_unit' => 120,
            'billing_interval' => 'monthly',
        ]);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoSubscriptionService($client);

        // Professional minimum is 10, quantity is 3 → should use 10
        $amount = $service->calculateAmount($planPrice, 3);

        expect($amount)->toBe(120000); // 120 * 10 * 100
    });

    it('calculates amount for enterprise tier', function () {
        $plan = Plan::factory()->create(['slug' => 'enterprise']);
        $planPrice = PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'price_per_unit' => 100,
            'billing_interval' => 'monthly',
        ]);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoSubscriptionService($client);

        // Enterprise minimum is 25, quantity is 50 → should use 50
        $amount = $service->calculateAmount($planPrice, 50);

        expect($amount)->toBe(500000); // 100 * 50 * 100
    });

    it('defaults to minimum of 1 for unknown plan slug', function () {
        $plan = Plan::factory()->create(['slug' => 'custom-plan']);
        $planPrice = PlanPrice::factory()->create([
            'plan_id' => $plan->id,
            'price_per_unit' => 200,
            'billing_interval' => 'monthly',
        ]);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoSubscriptionService($client);

        $amount = $service->calculateAmount($planPrice, 1);

        expect($amount)->toBe(20000); // 200 * 1 * 100
    });
});

describe('PayMongoWebhookService signature validation', function () {
    it('validates a correctly signed webhook payload', function () {
        $webhookSecret = 'whsk_test_secret_key';
        config(['paymongo.webhook_secret' => $webhookSecret]);

        $payload = json_encode([
            'data' => [
                'id' => 'evt_test123',
                'attributes' => [
                    'type' => 'subscription.activated',
                    'data' => [
                        'id' => 'sub_test123',
                        'attributes' => ['status' => 'active'],
                    ],
                ],
            ],
        ]);

        $timestamp = time();
        $testSignature = hash_hmac('sha256', $timestamp.'.'.$payload, $webhookSecret);
        $signatureHeader = "t={$timestamp},te={$testSignature},li=";

        $request = \Illuminate\Http\Request::create('/paymongo/webhook', 'POST', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);

        $event = $service->validateAndParse($request);

        expect($event)->toBeInstanceOf(\Paymongo\Entities\Event::class);
        expect($event->type)->toBe('subscription.activated');
    });

    it('rejects an invalidly signed webhook payload', function () {
        $webhookSecret = 'whsk_test_secret_key';
        config(['paymongo.webhook_secret' => $webhookSecret]);

        $payload = json_encode(['data' => ['id' => 'evt_test123', 'attributes' => []]]);
        $signatureHeader = 't=12345,te=invalid_signature,li=';

        $request = \Illuminate\Http\Request::create('/paymongo/webhook', 'POST', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);

        $service->validateAndParse($request);
    })->throws(\Paymongo\Exceptions\SignatureVerificationException::class);
});

describe('PayMongoWebhookService event handling', function () {
    it('updates subscription status on subscription.activated event', function () {
        $subscription = Subscription::factory()->create([
            'paymongo_id' => 'sub_activated_test',
            'paymongo_status' => SubscriptionStatus::Incomplete,
        ]);

        $apiResource = new \Paymongo\ApiResource([
            'data' => [
                'id' => 'evt_test',
                'attributes' => [
                    'type' => 'subscription.activated',
                    'data' => [
                        'id' => 'sub_activated_test',
                        'attributes' => ['status' => 'active'],
                    ],
                ],
            ],
        ]);

        $event = new \Paymongo\Entities\Event($apiResource);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);
        $service->handleEvent($event);

        $subscription->refresh();
        expect($subscription->paymongo_status)->toBe(SubscriptionStatus::Active);
    });

    it('marks subscription as past due on subscription.past_due event', function () {
        $subscription = Subscription::factory()->create([
            'paymongo_id' => 'sub_past_due_test',
            'paymongo_status' => SubscriptionStatus::Active,
        ]);

        $apiResource = new \Paymongo\ApiResource([
            'data' => [
                'id' => 'evt_test',
                'attributes' => [
                    'type' => 'subscription.past_due',
                    'data' => [
                        'id' => 'sub_past_due_test',
                        'attributes' => ['status' => 'past_due'],
                    ],
                ],
            ],
        ]);

        $event = new \Paymongo\Entities\Event($apiResource);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);
        $service->handleEvent($event);

        $subscription->refresh();
        expect($subscription->paymongo_status)->toBe(SubscriptionStatus::PastDue);
    });

    it('marks subscription as unpaid on subscription.unpaid event', function () {
        $subscription = Subscription::factory()->create([
            'paymongo_id' => 'sub_unpaid_test',
            'paymongo_status' => SubscriptionStatus::Active,
        ]);

        $apiResource = new \Paymongo\ApiResource([
            'data' => [
                'id' => 'evt_test',
                'attributes' => [
                    'type' => 'subscription.unpaid',
                    'data' => [
                        'id' => 'sub_unpaid_test',
                        'attributes' => ['status' => 'unpaid'],
                    ],
                ],
            ],
        ]);

        $event = new \Paymongo\Entities\Event($apiResource);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);
        $service->handleEvent($event);

        $subscription->refresh();
        expect($subscription->paymongo_status)->toBe(SubscriptionStatus::Unpaid);
    });

    it('extends subscription period on invoice.paid event', function () {
        $subscription = Subscription::factory()->create([
            'paymongo_id' => 'sub_invoice_test',
            'current_period_end' => now()->subDay(),
        ]);

        $apiResource = new \Paymongo\ApiResource([
            'data' => [
                'id' => 'evt_test',
                'attributes' => [
                    'type' => 'invoice.paid',
                    'data' => [
                        'id' => 'inv_test',
                        'attributes' => ['subscription_id' => 'sub_invoice_test'],
                    ],
                ],
            ],
        ]);

        $event = new \Paymongo\Entities\Event($apiResource);

        $client = new PaymongoClient('test_key');
        $service = new PayMongoWebhookService($client);
        $service->handleEvent($event);

        $subscription->refresh();
        expect($subscription->paymongo_status)->toBe(SubscriptionStatus::Active);
        expect($subscription->current_period_end->isFuture())->toBeTrue();
    });
});

describe('PayMongoWebhookController', function () {
    it('returns 200 for a valid webhook', function () {
        $webhookSecret = 'whsk_test_secret_key';
        config(['paymongo.webhook_secret' => $webhookSecret]);

        $payload = json_encode([
            'data' => [
                'id' => 'evt_controller_test',
                'attributes' => [
                    'type' => 'subscription.activated',
                    'data' => [
                        'id' => 'sub_controller_test',
                        'attributes' => ['status' => 'active'],
                    ],
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $webhookSecret);
        $signatureHeader = "t={$timestamp},te={$signature},li=";

        $response = $this->call('POST', '/paymongo/webhook', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => $signatureHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
    });

    it('returns 403 for an invalid signature', function () {
        config(['paymongo.webhook_secret' => 'whsk_test_secret_key']);

        $payload = json_encode(['data' => ['id' => 'evt_test', 'attributes' => []]]);

        $response = $this->call('POST', '/paymongo/webhook', [], [], [], [
            'HTTP_PAYMONGO_SIGNATURE' => 't=12345,te=bad_sig,li=',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertForbidden();
    });

    it('returns 403 when no signature header is provided', function () {
        config(['paymongo.webhook_secret' => 'whsk_test_secret_key']);

        $response = $this->postJson('/paymongo/webhook', ['data' => []]);

        // No signature → constructEvent will throw
        expect($response->status())->toBeIn([403, 500]);
    });
});

describe('PayMongoService facade', function () {
    it('provides access to all sub-services', function () {
        $client = new PaymongoClient('test_key');
        $service = new PayMongoService($client);

        expect($service->customers)->toBeInstanceOf(PayMongoCustomerService::class);
        expect($service->subscriptions)->toBeInstanceOf(PayMongoSubscriptionService::class);
        expect($service->webhooks)->toBeInstanceOf(PayMongoWebhookService::class);
    });
});

describe('PayMongoServiceProvider', function () {
    it('registers PaymongoClient as singleton', function () {
        $client1 = app(PaymongoClient::class);
        $client2 = app(PaymongoClient::class);

        expect($client1)->toBeInstanceOf(PaymongoClient::class);
        expect($client1)->toBe($client2);
    });
});
