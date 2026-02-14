<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    /**
     * Display a paginated listing of announcements.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Announcement::query()
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return AnnouncementResource::collection($query->paginate(15));
    }

    /**
     * Store a newly created announcement.
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['tenant_id'] = app('tenant')->id;

        if (! isset($data['published_at']) || $data['published_at'] === null) {
            $data['published_at'] = now();
        }

        $announcement = Announcement::create($data);

        return (new AnnouncementResource($announcement))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified announcement.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        Gate::authorize('can-manage-organization');

        $announcement->update($request->validated());

        return new AnnouncementResource($announcement);
    }

    /**
     * Remove the specified announcement.
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }
}
