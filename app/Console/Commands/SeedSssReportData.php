<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\SssReportSampleDataSeeder;
use Illuminate\Console\Command;

class SeedSssReportData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sss:seed-sample-data {tenant? : The tenant slug to seed data for}';

    /**
     * The console command description.
     */
    protected $description = 'Seed sample SSS report data (payroll entries and loans) for a tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found. Please create a tenant first.');

            return self::FAILURE;
        }

        $tenantSlug = $this->argument('tenant');

        if (! $tenantSlug) {
            $tenantSlug = $this->choice(
                'Which tenant do you want to seed SSS report data for?',
                $tenants->pluck('name', 'slug')->toArray(),
                $tenants->first()?->slug
            );
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            $this->error("Tenant with slug '{$tenantSlug}' not found.");
            $this->info('Available tenants: '.$tenants->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        $this->info("Seeding SSS report sample data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        // Check if employees exist
        $employeeCount = Employee::whereNotNull('sss_number')->count();
        if ($employeeCount === 0) {
            $this->error('No employees with SSS numbers found. Please run TenantSampleDataSeeder first.');

            return self::FAILURE;
        }

        $this->info("Found {$employeeCount} employees with SSS numbers.");

        // Run the seeder
        $seeder = new SssReportSampleDataSeeder;
        $seeder->setCommand($this);
        $seeder->run($tenantSlug);

        return self::SUCCESS;
    }
}
