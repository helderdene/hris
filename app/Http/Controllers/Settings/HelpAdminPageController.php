<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\HelpArticleResource;
use App\Http\Resources\HelpCategoryResource;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HelpAdminPageController extends Controller
{
    /**
     * Display the help admin settings page.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('is-super-admin');

        $categories = HelpCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->get();

        $articlesQuery = HelpArticle::query()
            ->with('category')
            ->orderBy('sort_order');

        if ($request->filled('category_id')) {
            $articlesQuery->where('help_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $articlesQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $articles = $articlesQuery->paginate(20)->withQueryString();

        return Inertia::render('settings/HelpAdmin/Index', [
            'categories' => HelpCategoryResource::collection($categories),
            'articles' => HelpArticleResource::collection($articles),
            'filters' => [
                'category_id' => $request->input('category_id'),
                'search' => $request->input('search'),
            ],
        ]);
    }
}
