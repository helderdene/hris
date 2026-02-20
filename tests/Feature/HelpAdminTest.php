<?php

use App\Enums\TenantUserRole;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

    $this->regularUser = User::factory()->create(['is_super_admin' => false]);
    $this->regularUser->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
    $this->superAdmin->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    $this->category = HelpCategory::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'description' => 'Test description',
        'sort_order' => 1,
        'is_active' => true,
    ]);
});

describe('Help Admin Page Access', function () {
    it('denies access to regular users', function () {
        $response = $this->actingAs($this->regularUser)->get("{$this->baseUrl}/settings/help-admin");

        $response->assertForbidden();
    });

    it('allows access to super admins', function () {
        $response = $this->actingAs($this->superAdmin)->get("{$this->baseUrl}/settings/help-admin");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/HelpAdmin/Index')
                ->has('categories')
                ->has('articles')
                ->has('filters')
        );
    });
});

describe('Category API', function () {
    it('denies category list to regular users', function () {
        $response = $this->actingAs($this->regularUser)->getJson("{$this->baseUrl}/api/help/categories");

        $response->assertForbidden();
    });

    it('allows super admin to list categories', function () {
        $response = $this->actingAs($this->superAdmin)->getJson("{$this->baseUrl}/api/help/categories");

        $response->assertOk();
        $response->assertJsonCount(1);
    });

    it('allows super admin to create category', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/categories", [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'New description',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'New Category');
        $this->assertDatabaseHas('help_categories', ['slug' => 'new-category']);
    });

    it('validates category creation', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/categories", [
            'name' => '',
            'slug' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'slug']);
    });

    it('prevents duplicate category slugs', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/categories", [
            'name' => 'Duplicate',
            'slug' => 'test-category',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    });

    it('allows super admin to update category', function () {
        $response = $this->actingAs($this->superAdmin)->putJson("{$this->baseUrl}/api/help/categories/{$this->category->id}", [
            'name' => 'Updated Category',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Category');
    });

    it('allows super admin to delete empty category', function () {
        $response = $this->actingAs($this->superAdmin)->deleteJson("{$this->baseUrl}/api/help/categories/{$this->category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('help_categories', ['id' => $this->category->id]);
    });

    it('prevents deleting category with articles', function () {
        HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->deleteJson("{$this->baseUrl}/api/help/categories/{$this->category->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('help_categories', ['id' => $this->category->id]);
    });
});

describe('Article API', function () {
    it('denies article list to regular users', function () {
        $response = $this->actingAs($this->regularUser)->getJson("{$this->baseUrl}/api/help/articles");

        $response->assertForbidden();
    });

    it('allows super admin to list articles', function () {
        $response = $this->actingAs($this->superAdmin)->getJson("{$this->baseUrl}/api/help/articles");

        $response->assertOk();
    });

    it('allows super admin to create article', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/articles", [
            'help_category_id' => $this->category->id,
            'title' => 'New Article',
            'slug' => 'new-article',
            'content' => '<p>Article content</p>',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('title', 'New Article');
        $this->assertDatabaseHas('help_articles', ['slug' => 'new-article']);
    });

    it('validates article creation', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/articles", [
            'title' => '',
            'content' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['help_category_id', 'title', 'slug', 'content']);
    });

    it('prevents duplicate article slugs in same category', function () {
        HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Existing Article',
            'slug' => 'existing-article',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/articles", [
            'help_category_id' => $this->category->id,
            'title' => 'Duplicate Article',
            'slug' => 'existing-article',
            'content' => 'Content',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    });

    it('allows same slug in different categories', function () {
        $otherCategory = HelpCategory::create([
            'name' => 'Other Category',
            'slug' => 'other-category',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Article 1',
            'slug' => 'same-slug',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/articles", [
            'help_category_id' => $otherCategory->id,
            'title' => 'Article 2',
            'slug' => 'same-slug',
            'content' => 'Content',
        ]);

        $response->assertCreated();
    });

    it('allows super admin to update article', function () {
        $article = HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Original Title',
            'slug' => 'original',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->putJson("{$this->baseUrl}/api/help/articles/{$article->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'Updated Title');
    });

    it('allows super admin to delete article', function () {
        $article = HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->deleteJson("{$this->baseUrl}/api/help/articles/{$article->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('help_articles', ['id' => $article->id]);
    });

    it('filters articles by category', function () {
        HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Article 1',
            'slug' => 'article-1',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("{$this->baseUrl}/api/help/articles?category_id={$this->category->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });

    it('sanitizes HTML content when creating article', function () {
        $response = $this->actingAs($this->superAdmin)->postJson("{$this->baseUrl}/api/help/articles", [
            'help_category_id' => $this->category->id,
            'title' => 'Sanitize Test',
            'slug' => 'sanitize-test',
            'content' => '<p>Safe content</p><script>alert("xss")</script><h1>Heading</h1>',
            'is_active' => true,
        ]);

        $response->assertCreated();

        $article = HelpArticle::where('slug', 'sanitize-test')->first();
        expect($article->content)->not->toContain('<script>');
        expect($article->content)->toContain('<p>Safe content</p>');
        expect($article->content)->toContain('<h1>Heading</h1>');
    });

    it('sanitizes HTML content when updating article', function () {
        $article = HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Update Sanitize',
            'slug' => 'update-sanitize',
            'content' => '<p>Original content</p>',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)->putJson("{$this->baseUrl}/api/help/articles/{$article->id}", [
            'content' => '<p>Updated</p><script>alert("xss")</script><img src="x" onerror="alert(1)">',
        ]);

        $response->assertOk();

        $article->refresh();
        expect($article->content)->not->toContain('<script>');
        expect($article->content)->not->toContain('onerror');
        expect($article->content)->toContain('<p>Updated</p>');
    });

    it('filters articles by search', function () {
        HelpArticle::create([
            'help_category_id' => $this->category->id,
            'title' => 'Unique Title XYZ',
            'slug' => 'unique-xyz',
            'content' => 'Content',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("{$this->baseUrl}/api/help/articles?search=Unique");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    });
});
