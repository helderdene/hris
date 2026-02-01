<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app('tenant')?->id ?? 1;

        Announcement::factory()->count(3)->create(['tenant_id' => $tenantId]);
        Announcement::factory()->pinned()->create([
            'tenant_id' => $tenantId,
            'title' => 'Welcome to the Employee Self-Service Portal',
            'body' => 'You can now view your payslips, DTR, leave balances, and more from your dashboard.',
        ]);
        Announcement::factory()->expired()->create(['tenant_id' => $tenantId]);
    }
}
