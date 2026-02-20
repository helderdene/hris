<?php

namespace App\Services\Billing;

use Paymongo\Services\BaseService;

/**
 * PayMongo Subscription Plan API service.
 *
 * Extends the SDK's BaseService to cover the `/v1/subscriptions/plans`
 * endpoints that are not included in the official SDK.
 *
 * @property \Paymongo\HttpClient $httpClient
 * @property \Paymongo\PaymongoClient $client
 */
class PayMongoSubscriptionPlanService extends BaseService
{
    private const URI = '/subscriptions/plans';

    /**
     * Create a new subscription plan on PayMongo.
     *
     * @param  array{name: string, amount: int, currency: string, interval: string, description?: string}  $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $apiResource = $this->httpClient->request([
            'method' => 'POST',
            'url' => "{$this->client->apiBaseUrl}/{$this->client->apiVersion}".self::URI,
            'params' => $params,
        ]);

        return (array) $apiResource->data;
    }

    /**
     * Retrieve a subscription plan by ID.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $id): array
    {
        $apiResource = $this->httpClient->request([
            'method' => 'GET',
            'url' => "{$this->client->apiBaseUrl}/{$this->client->apiVersion}".self::URI."/{$id}",
        ]);

        return (array) $apiResource->data;
    }

    /**
     * Update a subscription plan.
     *
     * @param  array{name?: string, amount?: int, description?: string}  $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        $apiResource = $this->httpClient->request([
            'method' => 'PUT',
            'url' => "{$this->client->apiBaseUrl}/{$this->client->apiVersion}".self::URI."/{$id}",
            'params' => $params,
        ]);

        return (array) $apiResource->data;
    }
}
