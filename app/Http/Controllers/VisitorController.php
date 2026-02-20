<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\VisitorVisit;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitorController extends Controller
{
    /**
     * Display the visitor management dashboard with tabs.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        return Inertia::render('Visitors/Index', [
            'locations' => fn () => WorkLocation::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'pendingCount' => fn () => VisitorVisit::pending()->count(),
            'todayCount' => fn () => VisitorVisit::today()->count(),
            'checkedInCount' => fn () => VisitorVisit::active()->count(),
        ]);
    }

    /**
     * Display the visitor log page.
     */
    public function log(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        return Inertia::render('Visitors/Log', [
            'locations' => fn () => WorkLocation::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => fn () => collect(VisitStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }
}
