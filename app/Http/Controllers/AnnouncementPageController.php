<?php

namespace App\Http\Controllers;

use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementPageController extends Controller
{
    public function __invoke(Request $request): Response
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

        return Inertia::render('Announcements/Index', [
            'announcements' => AnnouncementResource::collection($query->paginate(15)),
        ]);
    }
}
