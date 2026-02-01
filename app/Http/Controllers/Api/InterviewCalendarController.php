<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Services\InterviewCalendarService;
use Illuminate\Http\Response;

class InterviewCalendarController extends Controller
{
    public function __construct(
        protected InterviewCalendarService $calendarService
    ) {}

    /**
     * Download an iCal file for an interview.
     */
    public function download(string $tenant, Interview $interview): Response
    {
        $ics = $this->calendarService->generateIcs($interview);
        $filename = 'interview-'.$interview->id.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
