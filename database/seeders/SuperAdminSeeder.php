<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the super admin account.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@kasamahr.test'],
            [
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );
    }
}
