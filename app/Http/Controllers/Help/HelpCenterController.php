<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpArticleResource;
use App\Http\Resources\HelpCategoryResource;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
    /**
     * Display the help center homepage with categories and featured articles.
     */
    public function index(): Response
    {
        $categories = HelpCategory::query()
            ->active()
            ->ordered()
            ->withCount(['articles' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        $featuredArticles = HelpArticle::query()
            ->active()
            ->featured()
            ->with('category')
            ->ordered()
            ->limit(6)
            ->get();

        return Inertia::render('Help/Index', [
            'categories' => HelpCategoryResource::collection($categories),
            'featuredArticles' => HelpArticleResource::collection($featuredArticles),
        ]);
    }

    /**
     * Display search results for help articles.
     */
    public function search(Request $request): Response
    {
        $query = $request->input('q', '');

        $articles = collect();

        if (strlen($query) >= 2) {
            $articles = HelpArticle::query()
                ->active()
                ->search($query)
                ->with('category')
                ->limit(50)
                ->get();
        }

        $categories = HelpCategory::query()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Help/Search', [
            'query' => $query,
            'articles' => HelpArticleResource::collection($articles),
            'categories' => HelpCategoryResource::collection($categories),
        ]);
    }

    /**
     * Display a category with its articles.
     */
    public function showCategory(string $tenant, string $categorySlug): Response
    {
        $category = HelpCategory::query()
            ->where('slug', $categorySlug)
            ->active()
            ->firstOrFail();

        $articles = $category->activeArticles()
            ->ordered()
            ->get();

        $categories = HelpCategory::query()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Help/Category', [
            'category' => new HelpCategoryResource($category),
            'articles' => HelpArticleResource::collection($articles),
            'categories' => HelpCategoryResource::collection($categories),
        ]);
    }

    /**
     * Display a single article.
     */
    public function showArticle(string $tenant, string $categorySlug, string $articleSlug): Response
    {
        $category = HelpCategory::query()
            ->where('slug', $categorySlug)
            ->active()
            ->firstOrFail();

        $article = HelpArticle::query()
            ->where('help_category_id', $category->id)
            ->where('slug', $articleSlug)
            ->active()
            ->firstOrFail();

        // Increment view count
        $article->incrementViewCount();

        // Get related articles
        $relatedArticles = $article->relatedArticles();

        // Get previous and next articles in the same category
        $categoryArticles = $category->activeArticles()
            ->ordered()
            ->get();

        $currentIndex = $categoryArticles->search(function ($item) use ($article) {
            return $item->id === $article->id;
        });

        $previousArticle = $currentIndex > 0
            ? $categoryArticles[$currentIndex - 1]
            : null;

        $nextArticle = $currentIndex < $categoryArticles->count() - 1
            ? $categoryArticles[$currentIndex + 1]
            : null;

        $categories = HelpCategory::query()
            ->active()
            ->ordered()
            ->get();

        return Inertia::render('Help/Article', [
            'article' => new HelpArticleResource($article->load('category')),
            'category' => new HelpCategoryResource($category),
            'relatedArticles' => HelpArticleResource::collection($relatedArticles),
            'previousArticle' => $previousArticle ? new HelpArticleResource($previousArticle->load('category')) : null,
            'nextArticle' => $nextArticle ? new HelpArticleResource($nextArticle->load('category')) : null,
            'categories' => HelpCategoryResource::collection($categories),
        ]);
    }
}
