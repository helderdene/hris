<?php

namespace App\Services\Billing;

use App\Models\Tenant;
use Paymongo\Entities\Customer;
use Paymongo\PaymongoClient;

class PayMongoCustomerService
{
    public function __construct(public PaymongoClient $client) {}

    /**
     * Get existing PayMongo customer or create one for the tenant.
     */
    public function createOrGet(Tenant $tenant): Customer
    {
        if ($tenant->paymongo_customer_id) {
            return $this->client->customers->retrieve($tenant->paymongo_customer_id);
        }

        $customer = $this->client->customers->create([
            'first_name' => $tenant->name,
            'last_name' => 'Organization',
            'email' => $tenant->users()->first()?->email ?? '',
            'phone' => '',
        ]);

        $tenant->update(['paymongo_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Update a tenant's PayMongo customer attributes.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string}  $attributes
     */
    public function update(Tenant $tenant, array $attributes): Customer
    {
        if (! $tenant->paymongo_customer_id) {
            return $this->createOrGet($tenant);
        }

        return $this->client->customers->update($tenant->paymongo_customer_id, $attributes);
    }
}
