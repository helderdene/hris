<?php

namespace App\Enums;

/**
 * Provisioning state of a newly registered tenant.
 *
 * A tenant with no status is fully provisioned.
 */
enum TenantProvisioningStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
}
