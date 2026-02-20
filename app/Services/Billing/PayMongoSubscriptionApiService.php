<?php

namespace App\Services\Billing;

use Paymongo\Services\BaseService;

/**
 * PayMongo Subscription API service.
 *
 * Extends the SDK's BaseService to cover the `/v1/subscriptions`
 * endpoints that are not included in the official SDK.
 *
 * @property \Paymongo\HttpClient $httpClient
 * @property \Paymongo\PaymongoClient $client
 */
class PayMongoSubscriptionApiService extends BaseService
{
    private const URI = '/subscriptions';

    /**
     * Create a new subscription on PayMongo.
     *
     * @param  array{customer_id: string, plan_id: string, description?: string}  $params
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
     * Retrieve a subscription by ID.
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
     * Update a subscription.
     *
     * @param  array{plan_id?: string, description?: string}  $params
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

    /**
     * Cancel a subscription.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        $apiResource = $this->httpClient->request([
            'method' => 'POST',
            'url' => "{$this->client->apiBaseUrl}/{$this->client->apiVersion}".self::URI."/{$id}/cancel",
        ]);

        return (array) $apiResource->data;
    }
}
