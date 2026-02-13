<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\DocumentCategorySeeder;
use Illuminate\Console\Command;

class SeedDocumentCategories extends Command
{
    protected $signature = 'tenant:seed-document-categories {tenant? : The tenant slug to seed categories for (omit to seed all tenants)}';

    protected $description = 'Seed predefined document categories for tenant(s)';

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

            $this->info("Seeding document categories for all {$tenants->count()} tenant(s)...");
        }

        $seeder = new DocumentCategorySeeder;

        foreach ($tenants as $tenant) {
            $dbManager->switchConnection($tenant);
            $seeder->run();
            $this->info("Seeded document categories for: {$tenant->name} ({$tenant->slug})");
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
