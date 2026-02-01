<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('validates slug uniqueness', function () {
    Tenant::factory()->create(['slug' => 'acme-corp']);

    expect(fn () => Tenant::factory()->create(['slug' => 'acme-corp']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates slug is URL-safe format', function () {
    $tenant = Tenant::factory()->create(['slug' => 'valid-slug-123']);

    expect($tenant->slug)->toBe('valid-slug-123');
});

it('has many-to-many relationship with users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, ['role' => 'admin']);

    expect($tenant->users)->toHaveCount(1);
    expect($tenant->users->first()->id)->toBe($user->id);
    expect($tenant->users->first()->pivot->role)->toBe(TenantUserRole::Admin);
});

it('casts JSON fields correctly', function () {
    $businessInfo = ['company_name' => 'ACME Corp', 'address' => '123 Main St', 'tin' => '123-456-789'];
    $payrollSettings = ['pay_frequency' => 'semi-monthly', 'cutoff_day' => 15];
    $leaveDefaults = ['vacation_days' => 15, 'sick_days' => 10];

    $tenant = Tenant::factory()->create([
        'business_info' => $businessInfo,
        'payroll_settings' => $payrollSettings,
        'leave_defaults' => $leaveDefaults,
    ]);

    $tenant->refresh();

    expect($tenant->business_info)->toBeArray();
    expect($tenant->business_info['company_name'])->toBe('ACME Corp');
    expect($tenant->payroll_settings)->toBeArray();
    expect($tenant->payroll_settings['pay_frequency'])->toBe('semi-monthly');
    expect($tenant->leave_defaults)->toBeArray();
    expect($tenant->leave_defaults['vacation_days'])->toBe(15);
});

it('defaults timezone to Asia/Manila', function () {
    $tenant = Tenant::factory()->create(['timezone' => null]);

    expect($tenant->timezone)->toBe('Asia/Manila');
});

it('allows user to belong to multiple tenants', function () {
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create(['slug' => 'tenant-one']);
    $tenant2 = Tenant::factory()->create(['slug' => 'tenant-two']);

    $user->tenants()->attach($tenant1->id, ['role' => 'admin']);
    $user->tenants()->attach($tenant2->id, ['role' => 'employee']);

    expect($user->tenants)->toHaveCount(2);
    expect($user->tenants->pluck('slug')->toArray())->toContain('tenant-one', 'tenant-two');
});
