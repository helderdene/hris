<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __invoke(): Response
    {
        $tenant = app('tenant');

        $announcements = Announcement::query()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(15);

        return Inertia::render('My/Announcements', [
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }
}
