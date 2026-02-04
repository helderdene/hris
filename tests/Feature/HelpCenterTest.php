<?php

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->category = HelpCategory::create([
        'name' => 'Getting Started',
        'slug' => 'getting-started',
        'description' => 'Help getting started',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $this->article = HelpArticle::create([
        'help_category_id' => $this->category->id,
        'title' => 'Welcome Article',
        'slug' => 'welcome',
        'excerpt' => 'Welcome to the help center',
        'content' => '<p>Welcome content here</p>',
        'sort_order' => 1,
        'is_active' => true,
        'is_featured' => true,
    ]);
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/help');

    $response->assertRedirect('/login');
});

it('shows help center index to authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/help');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('categories')
            ->has('featuredArticles')
    );
});

it('shows category page with articles', function () {
    $response = $this->actingAs($this->user)->get('/help/getting-started');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Category')
            ->has('category')
            ->has('articles')
            ->has('categories')
    );
});

it('returns 404 for inactive category', function () {
    $this->category->update(['is_active' => false]);

    $response = $this->actingAs($this->user)->get('/help/getting-started');

    $response->assertNotFound();
});

it('shows article page and increments view count', function () {
    $initialViewCount = $this->article->view_count;

    $response = $this->actingAs($this->user)->get('/help/getting-started/welcome');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Article')
            ->has('article')
            ->has('category')
            ->has('relatedArticles')
            ->has('previousArticle')
            ->has('nextArticle')
            ->has('categories')
    );

    $this->article->refresh();
    expect($this->article->view_count)->toBe($initialViewCount + 1);
});

it('returns 404 for inactive article', function () {
    $this->article->update(['is_active' => false]);

    $response = $this->actingAs($this->user)->get('/help/getting-started/welcome');

    $response->assertNotFound();
});

it('shows search results for valid query', function () {
    $response = $this->actingAs($this->user)->get('/help/search?q=welcome');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Search')
            ->has('query')
            ->has('articles')
            ->has('categories')
    );
});

it('shows empty results for short query', function () {
    $response = $this->actingAs($this->user)->get('/help/search?q=a');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Search')
            ->where('articles', [])
    );
});

it('includes featured articles on index', function () {
    $response = $this->actingAs($this->user)->get('/help');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('featuredArticles', 1)
    );
});

it('only shows active categories', function () {
    $inactiveCategory = HelpCategory::create([
        'name' => 'Inactive Category',
        'slug' => 'inactive-category',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)->get('/help');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Index')
            ->has('categories', 1)
    );
});

it('only shows active articles in category', function () {
    $inactiveArticle = HelpArticle::create([
        'help_category_id' => $this->category->id,
        'title' => 'Inactive Article',
        'slug' => 'inactive-article',
        'content' => 'Content',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)->get('/help/getting-started');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Help/Category')
            ->has('articles', 1)
    );
});
