<?php

namespace App\Services;

use App\Models\Interview;

/**
 * Generates RFC 5545 iCal files for interviews.
 */
class InterviewCalendarService
{
    /**
     * Generate iCal content for an interview.
     */
    public function generateIcs(Interview $interview): string
    {
        $interview->load(['jobApplication.candidate', 'panelists.employee']);

        $start = $interview->scheduled_at->format('Ymd\THis\Z');
        $end = $interview->scheduled_at->addMinutes($interview->duration_minutes)->format('Ymd\THis\Z');
        $created = $interview->created_at->format('Ymd\THis\Z');
        $uid = "interview-{$interview->id}@kasamahr";
        $summary = $this->escapeIcal($interview->title);
        $candidate = $interview->jobApplication->candidate->full_name ?? 'Unknown';
        $description = $this->escapeIcal("Interview with {$candidate}");

        if ($interview->notes) {
            $description .= '\\n\\n'.$this->escapeIcal($interview->notes);
        }

        if ($interview->meeting_url) {
            $description .= '\\n\\nMeeting URL: '.$this->escapeIcal($interview->meeting_url);
        }

        $location = $interview->location
            ? $this->escapeIcal($interview->location)
            : ($interview->meeting_url ? $this->escapeIcal($interview->meeting_url) : '');

        $attendees = '';
        foreach ($interview->panelists as $panelist) {
            $name = $panelist->employee->full_name ?? 'Panelist';
            $attendees .= "ATTENDEE;CN={$name};ROLE=REQ-PARTICIPANT:MAILTO:noreply@kasamahr.com\r\n";
        }

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//KasaMaHR//Interview Scheduler//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:REQUEST\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTART:{$start}\r\n";
        $ics .= "DTEND:{$end}\r\n";
        $ics .= "DTSTAMP:{$created}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";

        if ($location) {
            $ics .= "LOCATION:{$location}\r\n";
        }

        $ics .= $attendees;
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    /**
     * Escape text for iCal format.
     */
    protected function escapeIcal(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\n", '\\n', $text);

        return str_replace("\r", '', $text);
    }
}
