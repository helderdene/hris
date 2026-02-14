<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\GovernmentContributionSeeder;
use Illuminate\Console\Command;

class SeedGovernmentContributions extends Command
{
    protected $signature = 'tenant:seed-contributions {tenant? : The tenant slug to seed contributions for (omit to seed all tenants)}';

    protected $description = 'Seed government contribution tables (SSS, PhilHealth, Pag-IBIG) for tenant(s)';

    public function handle(): int
    {
        $tenantSlug = $this->argument('tenant');
        $dbManager = app(TenantDatabaseManager::class);

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            if (! $tenant) {
                $this->error("Tenant with slug '{$tenantSlug}' not found.");

                return self::FAILURE;
            }

            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();

            if ($tenants->isEmpty()) {
                $this->error('No tenants found.');

                return self::FAILURE;
            }

            $this->info("Seeding government contributions for all {$tenants->count()} tenant(s)...");
        }

        $seeder = new GovernmentContributionSeeder;

        foreach ($tenants as $tenant) {
            $dbManager->switchConnection($tenant);
            $seeder->run();
            $this->info("Seeded contributions for: {$tenant->name} ({$tenant->slug})");
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
