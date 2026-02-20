<?php

namespace Database\Seeders;

use App\Enums\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the default subscription plans.
     *
     * Idempotent: uses updateOrCreate keyed on slug,
     * and deletes + recreates module assignments.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For small companies that need essential HR, payroll, and compliance tools.',
                'sort_order' => 0,
                'limits' => [
                    'max_employees' => 50,
                    'max_admin_users' => 3,
                    'max_departments' => 5,
                    'max_biometric_devices' => 2,
                    'max_kiosks' => 1,
                    'storage_gb' => 1,
                    'api_access' => false,
                ],
                'modules' => Module::starterModules(),
                'prices' => [
                    ['billing_interval' => 'monthly', 'price_per_unit' => 5000],
                    ['billing_interval' => 'yearly', 'price_per_unit' => 50000],
                ],
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing companies that need recruitment, training, and performance tools.',
                'sort_order' => 1,
                'limits' => [
                    'max_employees' => 250,
                    'max_admin_users' => 10,
                    'max_departments' => -1,
                    'max_biometric_devices' => 10,
                    'max_kiosks' => 5,
                    'storage_gb' => 10,
                    'api_access' => 'read_only',
                ],
                'modules' => Module::professionalModules(),
                'prices' => [
                    ['billing_interval' => 'monthly', 'price_per_unit' => 10000],
                    ['billing_interval' => 'yearly', 'price_per_unit' => 100000],
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large organizations needing full compliance, integrations, and employer branding.',
                'sort_order' => 2,
                'limits' => [
                    'max_employees' => -1,
                    'max_admin_users' => -1,
                    'max_departments' => -1,
                    'max_biometric_devices' => -1,
                    'max_kiosks' => -1,
                    'storage_gb' => 100,
                    'api_access' => 'full',
                ],
                'modules' => Module::enterpriseModules(),
                'prices' => [
                    ['billing_interval' => 'monthly', 'price_per_unit' => 15000],
                    ['billing_interval' => 'yearly', 'price_per_unit' => 150000],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $modules = $planData['modules'];
            $prices = $planData['prices'];
            unset($planData['modules'], $planData['prices']);

            $plan = Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );

            // Delete and recreate modules for idempotency
            $plan->modules()->delete();
            foreach ($modules as $module) {
                $plan->modules()->create(['module' => $module->value]);
            }

            // Upsert prices by billing_interval
            foreach ($prices as $priceData) {
                $plan->prices()->updateOrCreate(
                    ['billing_interval' => $priceData['billing_interval']],
                    $priceData,
                );
            }
        }
    }
}
