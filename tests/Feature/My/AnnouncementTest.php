<?php

use App\Models\Announcement;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('filters published announcements', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    // Published and active
    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'published_at' => now()->subDay(),
        'expires_at' => null,
    ]);

    // Published but expired
    Announcement::factory()->expired()->create(['tenant_id' => $tenant->id]);

    // Not yet published
    Announcement::factory()->unpublished()->create(['tenant_id' => $tenant->id]);

    // Scheduled for future
    Announcement::factory()->scheduled()->create(['tenant_id' => $tenant->id]);

    $published = Announcement::published()->get();

    expect($published)->toHaveCount(1);
});

it('includes announcements with future or null expiry', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'published_at' => now()->subDay(),
        'expires_at' => null,
    ]);

    Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'published_at' => now()->subDay(),
        'expires_at' => now()->addWeek(),
    ]);

    $published = Announcement::published()->get();

    expect($published)->toHaveCount(2);
});

it('casts attributes correctly', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $announcement = Announcement::factory()->create([
        'tenant_id' => $tenant->id,
        'is_pinned' => true,
    ]);

    expect($announcement->is_pinned)->toBeTrue();
    expect($announcement->published_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
