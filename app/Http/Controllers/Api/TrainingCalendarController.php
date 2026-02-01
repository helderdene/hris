<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingCalendarEntryResource;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingCalendarController extends Controller
{
    /**
     * Get calendar entries for a specific month.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-view-training');

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) $request->input('year');
        $month = (int) $request->input('month');

        $query = TrainingSession::query()
            ->with(['course'])
            ->inMonth($year, $month)
            ->orderBy('start_date')
            ->orderBy('start_time');

        // For employees without manage permission, only show visible sessions
        if (! Gate::allows('can-manage-training')) {
            $query->visibleToEmployees();
        }

        return TrainingCalendarEntryResource::collection($query->get());
    }
}
