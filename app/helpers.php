<?php

use App\Models\Tenant;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant from the application container.
     *
     * Returns the resolved tenant for the current request, or null
     * if no tenant has been resolved (e.g., on the main domain).
     */
    function tenant(): ?Tenant
    {
        if (app()->bound('tenant')) {
            return app('tenant');
        }

        return null;
    }
}
