<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHelpArticleRequest;
use App\Http\Requests\UpdateHelpArticleRequest;
use App\Http\Resources\HelpArticleResource;
use App\Models\HelpArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class HelpArticleController extends Controller
{
    /**
     * Display a listing of help articles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('is-super-admin');

        $query = HelpArticle::query()
            ->with('category')
            ->orderBy('sort_order');

        if ($request->filled('category_id')) {
            $query->where('help_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('featured_only')) {
            $query->featured();
        }

        $perPage = $request->input('per_page', 20);

        return HelpArticleResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created help article.
     */
    public function store(StoreHelpArticleRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = HelpArticle::where('help_category_id', $data['help_category_id'])
                ->max('sort_order') + 1;
        }

        $article = HelpArticle::create($data);

        return (new HelpArticleResource($article->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified help article.
     */
    public function show(string $tenant, HelpArticle $article): HelpArticleResource
    {
        Gate::authorize('is-super-admin');

        return new HelpArticleResource($article->load('category'));
    }

    /**
     * Update the specified help article.
     */
    public function update(UpdateHelpArticleRequest $request, string $tenant, HelpArticle $article): HelpArticleResource
    {
        $article->update($request->validated());

        return new HelpArticleResource($article->load('category'));
    }

    /**
     * Remove the specified help article.
     */
    public function destroy(string $tenant, HelpArticle $article): JsonResponse
    {
        Gate::authorize('is-super-admin');

        // Remove this article from related_article_ids of other articles
        HelpArticle::whereJsonContains('related_article_ids', $article->id)
            ->each(function ($relatedArticle) use ($article) {
                $relatedIds = $relatedArticle->related_article_ids ?? [];
                $relatedIds = array_values(array_filter($relatedIds, fn ($id) => $id !== $article->id));
                $relatedArticle->update(['related_article_ids' => $relatedIds ?: null]);
            });

        $article->delete();

        return response()->json([
            'message' => 'Help article deleted successfully.',
        ]);
    }
}
