<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Main database seeder.
 *
 * Available seeders for tenant databases:
 *
 * - TenantSampleDataSeeder: Seeds sample data for a tenant (includes PhilippineHolidaySeeder)
 *   Usage: php artisan db:seed --class=TenantSampleDataSeeder
 *
 * - PhilippineHolidaySeeder: Seeds Philippine national holidays for current and next year
 *   For existing tenants, run manually after switching to tenant database:
 *   Usage: php artisan db:seed --class=PhilippineHolidaySeeder --database=tenant
 *
 * - DocumentCategorySeeder: Seeds predefined document categories
 *   Usage: php artisan db:seed --class=DocumentCategorySeeder --database=tenant
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
