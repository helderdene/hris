<?php

namespace App\Models;

use App\Services\Tenant\TenantDatabaseManager;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model class for tenant-scoped models.
 *
 * Models that store data in tenant-specific databases should extend this class.
 * It ensures all queries are executed against the 'tenant' connection, which
 * is dynamically configured to point to the current tenant's database.
 *
 * In SQLite/testing environments, the model falls back to the default connection
 * for simplicity in test setups.
 *
 * Usage:
 * ```php
 * class Employee extends TenantModel
 * {
 *     protected $fillable = ['name', 'email', 'department'];
 * }
 * ```
 *
 * The TenantDatabaseManager service handles switching the 'tenant' connection
 * to the appropriate database based on the current request's subdomain.
 */
abstract class TenantModel extends Model
{
    use HasAuditTrail;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * Get the database connection for the model.
     * Uses 'tenant' connection in production/MySQL environments,
     * but falls back to default for SQLite/testing.
     */
    public function getConnectionName(): ?string
    {
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            return null;
        }

        return 'tenant';
    }

    /**
     * Resolve the route binding for tenant models.
     *
     * Ensures the tenant database connection is configured before
     * attempting to resolve the model from route parameters.
     * This is necessary because route model binding runs before
     * the tenant middleware has a chance to switch the database.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // Skip for SQLite (testing)
        if (config('database.default') === 'sqlite') {
            return parent::resolveRouteBinding($value, $field);
        }

        // Try to get tenant from container first
        $tenant = tenant();

        // If not bound yet, resolve from request subdomain
        if ($tenant === null) {
            $tenant = $this->resolveTenantFromRequest();
        }

        // Switch database connection if tenant found
        if ($tenant !== null) {
            app(TenantDatabaseManager::class)->switchConnection($tenant);
            app()->instance('tenant', $tenant);
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Resolve tenant from the current request's subdomain.
     */
    protected function resolveTenantFromRequest(): ?Tenant
    {
        $request = request();
        $host = $request->getHost();

        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // Extract subdomain
        $mainDomains = ['kasamahr.com', 'kasamahr.test', 'localhost'];
        foreach ($mainDomains as $mainDomain) {
            $pattern = '/^([a-z0-9-]+)\.'.preg_quote($mainDomain, '/').'$/i';
            if (preg_match($pattern, $host, $matches)) {
                $slug = strtolower($matches[1]);

                return Tenant::where('slug', $slug)->first();
            }
        }

        return null;
    }
}
