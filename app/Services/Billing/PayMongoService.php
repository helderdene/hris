<?php

namespace App\Services\Billing;

use Paymongo\PaymongoClient;

/**
 * Aggregator service providing access to all PayMongo billing services.
 */
class PayMongoService
{
    public PayMongoCustomerService $customers;

    public PayMongoSubscriptionService $subscriptions;

    public PayMongoWebhookService $webhooks;

    public function __construct(public PaymongoClient $client)
    {
        $this->customers = new PayMongoCustomerService($client);
        $this->subscriptions = new PayMongoSubscriptionService($client);
        $this->webhooks = new PayMongoWebhookService($client);
    }
}
