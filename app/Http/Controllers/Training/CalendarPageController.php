<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\TrainingCalendarEntryResource;
use App\Models\Course;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarPageController extends Controller
{
    /**
     * Display the training calendar page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-training');

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $sessions = TrainingSession::query()
            ->with(['course'])
            ->inMonth($year, $month)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $courses = Course::query()
            ->published()
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        return Inertia::render('Training/Calendar/Index', [
            'sessions' => TrainingCalendarEntryResource::collection($sessions),
            'courses' => CourseListResource::collection($courses),
            'currentYear' => $year,
            'currentMonth' => $month,
            'monthName' => Carbon::create($year, $month, 1)->format('F Y'),
        ]);
    }
}
