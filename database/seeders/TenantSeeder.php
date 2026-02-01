<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Company',
            'slug' => 'demo',
            'business_info' => [
                'company_name' => 'Demo Company Inc.',
                'address' => '123 Demo Street, Manila, Philippines',
                'tin' => '123-456-789-000',
            ],
        ]);

        $user = User::where('email', 'test@example.com')->first();

        if ($user) {
            $tenant->users()->attach($user->id, ['role' => 'admin']);
        }
    }
}
