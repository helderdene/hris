<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorRequest;
use App\Http\Requests\UpdateVisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class VisitorController extends Controller
{
    /**
     * Display a listing of visitors.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Visitor::query()
            ->withCount('visits')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $visitors = $query->paginate($request->integer('per_page', 25));

        return VisitorResource::collection($visitors);
    }

    /**
     * Store a newly created visitor.
     */
    public function store(StoreVisitorRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $visitor = Visitor::create($request->validated());

        return (new VisitorResource($visitor))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified visitor.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        Gate::authorize('can-manage-organization');

        $visitor->loadCount('visits');

        return new VisitorResource($visitor);
    }

    /**
     * Update the specified visitor.
     */
    public function update(UpdateVisitorRequest $request, Visitor $visitor): VisitorResource
    {
        Gate::authorize('can-manage-organization');

        $visitor->update($request->validated());

        return new VisitorResource($visitor);
    }

    /**
     * Remove the specified visitor.
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $visitor->delete();

        return response()->json([
            'message' => 'Visitor deleted successfully.',
        ]);
    }
}
