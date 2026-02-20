<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paymongo\Entities\Event;
use Paymongo\Exceptions\SignatureVerificationException;
use Paymongo\PaymongoClient;

class PayMongoWebhookService
{
    public function __construct(public PaymongoClient $client) {}

    /**
     * Validate the webhook signature and parse the event.
     *
     * @throws SignatureVerificationException
     */
    public function validateAndParse(Request $request): Event
    {
        $payload = $request->getContent();
        $signature = $request->header('Paymongo-Signature', '');

        return $this->client->webhooks->constructEvent([
            'payload' => $payload,
            'signature_header' => $signature,
            'webhook_secret_key' => config('paymongo.webhook_secret'),
        ]);
    }

    /**
     * Route the event to the appropriate handler.
     */
    public function handleEvent(Event $event): void
    {
        $type = $event->type;

        match ($type) {
            'subscription.activated' => $this->handleSubscriptionActivated($event),
            'subscription.past_due' => $this->handleSubscriptionPastDue($event),
            'subscription.unpaid' => $this->handleSubscriptionUnpaid($event),
            'subscription.updated' => $this->handleSubscriptionUpdated($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            default => Log::info("Unhandled PayMongo webhook event: {$type}"),
        };
    }

    /**
     * Handle subscription activated event.
     */
    private function handleSubscriptionActivated(Event $event): void
    {
        $subscriptionData = $event->resource;
        $paymongoId = $subscriptionData['id'] ?? null;

        if (! $paymongoId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $paymongoId)->first();

        if ($subscription) {
            $subscription->update([
                'paymongo_status' => SubscriptionStatus::Active,
            ]);
        }
    }

    /**
     * Handle subscription past due event.
     */
    private function handleSubscriptionPastDue(Event $event): void
    {
        $subscriptionData = $event->resource;
        $paymongoId = $subscriptionData['id'] ?? null;

        if (! $paymongoId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $paymongoId)->first();

        if ($subscription) {
            $subscription->update([
                'paymongo_status' => SubscriptionStatus::PastDue,
            ]);

            Log::warning("Subscription {$paymongoId} is past due for tenant {$subscription->tenant_id}");
        }
    }

    /**
     * Handle subscription unpaid event.
     */
    private function handleSubscriptionUnpaid(Event $event): void
    {
        $subscriptionData = $event->resource;
        $paymongoId = $subscriptionData['id'] ?? null;

        if (! $paymongoId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $paymongoId)->first();

        if ($subscription) {
            $subscription->update([
                'paymongo_status' => SubscriptionStatus::Unpaid,
            ]);

            Log::warning("Subscription {$paymongoId} is unpaid for tenant {$subscription->tenant_id}");
        }
    }

    /**
     * Handle subscription updated event.
     */
    private function handleSubscriptionUpdated(Event $event): void
    {
        $subscriptionData = $event->resource;
        $paymongoId = $subscriptionData['id'] ?? null;

        if (! $paymongoId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $paymongoId)->first();

        if ($subscription) {
            $attributes = $subscriptionData['attributes'] ?? [];
            $status = $attributes['status'] ?? null;

            if ($status && SubscriptionStatus::isValid($status)) {
                $subscription->update([
                    'paymongo_status' => SubscriptionStatus::from($status),
                ]);
            }
        }
    }

    /**
     * Handle invoice paid event — extend the subscription period.
     */
    private function handleInvoicePaid(Event $event): void
    {
        $invoiceData = $event->resource;
        $subscriptionId = $invoiceData['attributes']['subscription_id'] ?? null;

        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $subscriptionId)->first();

        if ($subscription) {
            $subscription->update([
                'paymongo_status' => SubscriptionStatus::Active,
                'current_period_end' => now()->addMonth(),
            ]);
        }
    }

    /**
     * Handle invoice payment failed event.
     */
    private function handleInvoicePaymentFailed(Event $event): void
    {
        $invoiceData = $event->resource;
        $subscriptionId = $invoiceData['attributes']['subscription_id'] ?? null;

        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('paymongo_id', $subscriptionId)->first();

        if ($subscription) {
            Log::error("Invoice payment failed for subscription {$subscriptionId}, tenant {$subscription->tenant_id}");
        }
    }
}
