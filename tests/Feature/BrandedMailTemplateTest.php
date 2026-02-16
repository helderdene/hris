<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewHireAccountSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
});

it('renders MailMessage notifications with branded layout', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Corp',
        'primary_color' => '#3b82f6',
    ]);
    app()->instance('tenant', $tenant);

    $user = User::factory()->create(['name' => 'John Doe']);

    $notification = new NewHireAccountSetup($tenant, 'test-token-123');
    $mailMessage = $notification->toMail($user);

    $rendered = renderMailMessage($mailMessage);

    // Accent bar with tenant primary color
    expect($rendered)->toContain('background-color: #3b82f6')
        ->and($rendered)->toContain('accent-bar');

    // Tenant name in header
    expect($rendered)->toContain('Acme Corp');

    // Branded footer
    expect($rendered)->toContain('Sent by')
        ->and($rendered)->toContain('on behalf of Acme Corp');

    // Rounded card (12px border-radius in CSS)
    expect($rendered)->toContain('border-radius: 12px');

    // Button with rounded corners
    expect($rendered)->toContain('border-radius: 8px');
});

it('uses default color when tenant has no primary color', function () {
    $tenant = Tenant::factory()->withoutBranding()->create([
        'name' => 'NoBrand Inc',
    ]);
    app()->instance('tenant', $tenant);

    $user = User::factory()->create();

    $notification = new NewHireAccountSetup($tenant, 'test-token');
    $mailMessage = $notification->toMail($user);

    $rendered = renderMailMessage($mailMessage);

    // Falls back to default dark color
    expect($rendered)->toContain('background-color: #111827');
});

it('falls back gracefully when no tenant is bound', function () {
    $tenant = Tenant::factory()->create(['name' => 'Test Org']);

    $user = User::factory()->create();

    $notification = new NewHireAccountSetup($tenant, 'test-token');
    $mailMessage = $notification->toMail($user);

    $rendered = renderMailMessage($mailMessage);

    // Should still render without errors, using defaults
    expect($rendered)->toContain('background-color: #111827')
        ->and($rendered)->toContain(config('app.name'));
});

it('renders button with tenant primary color', function () {
    $tenant = Tenant::factory()->create([
        'primary_color' => '#e11d48',
    ]);
    app()->instance('tenant', $tenant);

    $user = User::factory()->create();

    $notification = new NewHireAccountSetup($tenant, 'test-token');
    $mailMessage = $notification->toMail($user);

    $rendered = renderMailMessage($mailMessage);

    // Button inline style uses tenant color
    expect($rendered)->toContain('#e11d48');
});

it('uses light gray background matching invitation design', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant', $tenant);

    $user = User::factory()->create();

    $notification = new NewHireAccountSetup($tenant, 'test-token');
    $mailMessage = $notification->toMail($user);

    $rendered = renderMailMessage($mailMessage);

    expect($rendered)->toContain('#f4f5f7');
});

/**
 * Render a MailMessage to HTML string.
 */
function renderMailMessage(MailMessage $mailMessage): string
{
    $markdown = app(\Illuminate\Mail\Markdown::class);

    return $markdown->render($mailMessage->markdown, $mailMessage->data());
}
