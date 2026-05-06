<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Throwable;

class MigrateTenants extends Command
{
    protected $signature = 'tenant:migrate
        {tenant? : The tenant slug to migrate (omit to migrate all tenants)}
        {--continue-on-error : Keep going if a tenant migration fails}';

    protected $description = 'Run tenant-schema migrations (database/migrations/tenant) against one or all tenants';

    public function handle(TenantDatabaseManager $dbManager): int
    {
        $tenantSlug = $this->argument('tenant');
        $continueOnError = (bool) $this->option('continue-on-error');

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
                $this->warn('No tenants found.');

                return self::SUCCESS;
            }

            $this->info("Running tenant migrations for {$tenants->count()} tenant(s)...");
        }

        $failures = 0;

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("→ {$tenant->name} ({$tenant->slug})");

            try {
                $dbManager->migrateSchema($tenant);
                $output = trim((string) $this->laravel['Illuminate\Contracts\Console\Kernel']->output());
                if ($output !== '') {
                    $this->line($output);
                }
            } catch (Throwable $e) {
                $failures++;
                $this->error("  Failed: {$e->getMessage()}");

                if (! $continueOnError) {
                    $this->error('Aborting. Re-run with --continue-on-error to keep going past failures.');

                    return self::FAILURE;
                }
            }
        }

        $this->line('');

        if ($failures > 0) {
            $this->warn("Completed with {$failures} failure(s).");

            return self::FAILURE;
        }

        $this->info('All tenant migrations completed successfully.');

        return self::SUCCESS;
    }
}
