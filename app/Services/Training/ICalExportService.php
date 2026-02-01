<?php

namespace App\Services\Training;

use App\Models\Employee;
use App\Models\TrainingSession;
use Carbon\Carbon;

/**
 * Service for generating iCal calendar exports for training sessions.
 */
class ICalExportService
{
    /**
     * Generate an iCal file for all of an employee's enrolled sessions.
     */
    public function generateForEmployee(Employee $employee): string
    {
        $enrollments = $employee->activeTrainingEnrollments()
            ->with(['session.course'])
            ->get();

        $events = [];
        foreach ($enrollments as $enrollment) {
            $events[] = $this->formatSessionAsEvent($enrollment->session);
        }

        return $this->buildICalFile($events, "Training Calendar - {$employee->full_name}");
    }

    /**
     * Generate an iCal file for a single session.
     */
    public function generateForSession(TrainingSession $session): string
    {
        $session->load('course');

        $events = [$this->formatSessionAsEvent($session)];

        return $this->buildICalFile($events, $session->display_title);
    }

    /**
     * Format a training session as an iCal event.
     *
     * @return array<string, mixed>
     */
    protected function formatSessionAsEvent(TrainingSession $session): array
    {
        $startDateTime = $this->buildDateTime($session->start_date, $session->start_time);
        $endDateTime = $this->buildDateTime($session->end_date, $session->end_time);

        // If no time specified, make it an all-day event
        $allDay = $session->start_time === null;

        $description = [];
        if ($session->course) {
            $description[] = "Course: {$session->course->title}";
            if ($session->course->description) {
                $description[] = $session->course->description;
            }
        }
        if ($session->notes) {
            $description[] = "Notes: {$session->notes}";
        }
        if ($session->virtual_link) {
            $description[] = "Virtual Link: {$session->virtual_link}";
        }

        return [
            'uid' => "training-session-{$session->id}@".config('app.main_domain'),
            'summary' => $session->display_title,
            'description' => implode("\n\n", $description),
            'location' => $session->location ?? ($session->virtual_link ? 'Online' : ''),
            'start' => $startDateTime,
            'end' => $endDateTime,
            'all_day' => $allDay,
            'created' => $session->created_at,
            'modified' => $session->updated_at,
        ];
    }

    /**
     * Build a datetime from date and optional time.
     */
    protected function buildDateTime(Carbon $date, mixed $time): Carbon
    {
        if ($time === null) {
            return $date->startOfDay();
        }

        $timeCarbon = $time instanceof Carbon ? $time : Carbon::parse($time);

        return $date->copy()->setTime(
            $timeCarbon->hour,
            $timeCarbon->minute,
            0
        );
    }

    /**
     * Build the complete iCal file content.
     *
     * @param  array<array<string, mixed>>  $events
     */
    protected function buildICalFile(array $events, string $calendarName): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//KasamaHR//Training Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeText($calendarName),
        ];

        foreach ($events as $event) {
            $lines = array_merge($lines, $this->formatEvent($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * Format a single event for iCal.
     *
     * @param  array<string, mixed>  $event
     * @return array<string>
     */
    protected function formatEvent(array $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$event['uid'],
            'DTSTAMP:'.$this->formatDateTime(now()),
        ];

        if ($event['all_day']) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$event['start']->format('Ymd');
            // For all-day events, end date should be the day after
            $lines[] = 'DTEND;VALUE=DATE:'.$event['end']->addDay()->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$this->formatDateTime($event['start']);
            $lines[] = 'DTEND:'.$this->formatDateTime($event['end']);
        }

        $lines[] = 'SUMMARY:'.$this->escapeText($event['summary']);

        if (! empty($event['description'])) {
            $lines[] = 'DESCRIPTION:'.$this->escapeText($event['description']);
        }

        if (! empty($event['location'])) {
            $lines[] = 'LOCATION:'.$this->escapeText($event['location']);
        }

        if ($event['created']) {
            $lines[] = 'CREATED:'.$this->formatDateTime($event['created']);
        }

        if ($event['modified']) {
            $lines[] = 'LAST-MODIFIED:'.$this->formatDateTime($event['modified']);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Format a datetime for iCal (UTC).
     */
    protected function formatDateTime(Carbon $datetime): string
    {
        return $datetime->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escape text for iCal format.
     */
    protected function escapeText(string $text): string
    {
        // Replace newlines with \n
        $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);

        // Escape special characters
        $text = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $text);

        return $text;
    }
}
