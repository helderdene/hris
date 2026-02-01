<?php

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\TenantUserRole;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-onboarding', fn () => true);
});

/*
|--------------------------------------------------------------------------
| Template Model Tests
|--------------------------------------------------------------------------
*/

it('creates a template with items using factory', function () {
    $template = OnboardingTemplate::factory()
        ->has(
            OnboardingTemplateItem::factory()
                ->count(4)
                ->sequence(
                    ['category' => OnboardingCategory::Provisioning],
                    ['category' => OnboardingCategory::Equipment],
                    ['category' => OnboardingCategory::Orientation],
                    ['category' => OnboardingCategory::Training],
                ),
            'items'
        )
        ->create();

    expect($template->items)->toHaveCount(4);
    expect($template->items->pluck('category')->unique())->toHaveCount(4);
});

it('scopes to active templates', function () {
    OnboardingTemplate::factory()->create(['is_active' => true]);
    OnboardingTemplate::factory()->create(['is_active' => true]);
    OnboardingTemplate::factory()->create(['is_active' => false]);

    expect(OnboardingTemplate::active()->count())->toBe(2);
});

it('scopes to default template', function () {
    OnboardingTemplate::factory()->create(['is_default' => false]);
    $default = OnboardingTemplate::factory()->default()->create();

    expect(OnboardingTemplate::default()->first()->id)->toBe($default->id);
});

it('sorts items by sort_order', function () {
    $template = OnboardingTemplate::factory()->create();

    OnboardingTemplateItem::factory()->for($template, 'template')->create(['sort_order' => 3]);
    OnboardingTemplateItem::factory()->for($template, 'template')->create(['sort_order' => 1]);
    OnboardingTemplateItem::factory()->for($template, 'template')->create(['sort_order' => 2]);

    $sortOrders = $template->items->pluck('sort_order')->toArray();

    expect($sortOrders)->toBe([1, 2, 3]);
});

/*
|--------------------------------------------------------------------------
| Template Page Controller Tests
|--------------------------------------------------------------------------
*/

it('renders templates index page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    OnboardingTemplate::factory()->count(3)->create();

    $response = $this->get("{$this->baseUrl}/onboarding-templates");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Templates/Index')
        ->has('templates', 3)
    );
});

it('renders template create page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/onboarding-templates/create");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Templates/Form')
        ->where('template', null)
        ->has('categories')
        ->has('roles')
    );
});

it('renders template edit page', function () {
    $this->withoutVite();
    $this->actingAs($this->user);

    $template = OnboardingTemplate::factory()
        ->has(OnboardingTemplateItem::factory()->count(2), 'items')
        ->create();

    $response = $this->get("{$this->baseUrl}/onboarding-templates/{$template->id}/edit");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Templates/Form')
        ->where('template.id', $template->id)
        ->has('template.items', 2)
    );
});

/*
|--------------------------------------------------------------------------
| Template API Controller Tests
|--------------------------------------------------------------------------
*/

it('creates a new template via API', function () {
    $this->actingAs($this->user);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-templates", [
        'name' => 'Standard Onboarding',
        'description' => 'Default onboarding template for new employees',
        'is_default' => true,
        'is_active' => true,
        'items' => [
            [
                'category' => OnboardingCategory::Provisioning->value,
                'name' => 'Create email account',
                'description' => 'Set up corporate email',
                'assigned_role' => OnboardingAssignedRole::IT->value,
                'is_required' => true,
                'sort_order' => 0,
                'due_days_offset' => -1,
            ],
            [
                'category' => OnboardingCategory::Equipment->value,
                'name' => 'Assign laptop',
                'description' => 'Prepare and assign laptop',
                'assigned_role' => OnboardingAssignedRole::Admin->value,
                'is_required' => true,
                'sort_order' => 1,
                'due_days_offset' => 0,
            ],
        ],
    ]);

    $response->assertSuccessful();

    $template = OnboardingTemplate::where('name', 'Standard Onboarding')->first();
    expect($template)->not->toBeNull();
    expect($template->items)->toHaveCount(2);
    expect($template->is_default)->toBeTrue();
});

it('updates a template via API', function () {
    $this->actingAs($this->user);

    $template = OnboardingTemplate::factory()
        ->has(OnboardingTemplateItem::factory()->count(2), 'items')
        ->create(['name' => 'Old Name']);

    $response = $this->putJson("{$this->baseUrl}/api/onboarding-templates/{$template->id}", [
        'name' => 'Updated Name',
        'description' => 'Updated description',
        'is_default' => false,
        'is_active' => true,
        'items' => [
            [
                'category' => OnboardingCategory::Training->value,
                'name' => 'New training item',
                'description' => 'Complete onboarding training',
                'assigned_role' => OnboardingAssignedRole::HR->value,
                'is_required' => true,
                'sort_order' => 0,
                'due_days_offset' => 7,
            ],
        ],
    ]);

    $response->assertSuccessful();

    $template->refresh();
    expect($template->name)->toBe('Updated Name');
    expect($template->items)->toHaveCount(1);
    expect($template->items->first()->category)->toBe(OnboardingCategory::Training);
});

it('deletes a template via API', function () {
    $this->actingAs($this->user);

    $template = OnboardingTemplate::factory()->create();

    $response = $this->deleteJson("{$this->baseUrl}/api/onboarding-templates/{$template->id}");

    $response->assertSuccessful();
    expect(OnboardingTemplate::find($template->id))->toBeNull();
});

it('toggles template active status via API', function () {
    $this->actingAs($this->user);

    $template = OnboardingTemplate::factory()->create(['is_active' => true]);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-templates/{$template->id}/toggle-active");

    $response->assertSuccessful();
    $template->refresh();
    expect($template->is_active)->toBeFalse();

    // Toggle again
    $this->postJson("{$this->baseUrl}/api/onboarding-templates/{$template->id}/toggle-active");
    $template->refresh();
    expect($template->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Validation Tests
|--------------------------------------------------------------------------
*/

it('validates required fields when creating template', function () {
    $this->actingAs($this->user);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-templates", []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('validates template items have required fields', function () {
    $this->actingAs($this->user);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-templates", [
        'name' => 'Test Template',
        'items' => [
            ['category' => 'invalid'], // Missing required fields
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items.0.name', 'items.0.assigned_role']);
});

it('validates category is valid enum value', function () {
    $this->actingAs($this->user);

    $response = $this->postJson("{$this->baseUrl}/api/onboarding-templates", [
        'name' => 'Test Template',
        'items' => [
            [
                'category' => 'invalid_category',
                'name' => 'Test Item',
                'assigned_role' => OnboardingAssignedRole::IT->value,
                'is_required' => true,
                'sort_order' => 0,
                'due_days_offset' => 0,
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items.0.category']);
});

/*
|--------------------------------------------------------------------------
| Authorization Tests
|--------------------------------------------------------------------------
*/

it('requires authentication to access templates', function () {
    $response = $this->get("{$this->baseUrl}/onboarding-templates");

    $response->assertRedirect();
});

it('requires can-manage-organization permission', function () {
    $this->withoutVite();
    Gate::define('can-manage-organization', fn () => false);

    $this->actingAs($this->user);

    $response = $this->get("{$this->baseUrl}/onboarding-templates");

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Template Item Tests
|--------------------------------------------------------------------------
*/

it('creates template item with correct default role for category', function () {
    $template = OnboardingTemplate::factory()->create();

    $provisioningItem = OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->category(OnboardingCategory::Provisioning)
        ->create();

    $equipmentItem = OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->category(OnboardingCategory::Equipment)
        ->create();

    expect($provisioningItem->assigned_role)->toBe(OnboardingAssignedRole::IT);
    expect($equipmentItem->assigned_role)->toBe(OnboardingAssignedRole::Admin);
});

it('allows overriding assigned role', function () {
    $template = OnboardingTemplate::factory()->create();

    $item = OnboardingTemplateItem::factory()
        ->for($template, 'template')
        ->category(OnboardingCategory::Provisioning)
        ->assignedRole(OnboardingAssignedRole::HR)
        ->create();

    expect($item->category)->toBe(OnboardingCategory::Provisioning);
    expect($item->assigned_role)->toBe(OnboardingAssignedRole::HR);
});
