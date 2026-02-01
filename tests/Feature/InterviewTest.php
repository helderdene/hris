<?php

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewPanelist;
use App\Models\JobApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InterviewCalendarService;
use App\Services\InterviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
});

describe('Interview Model', function () {
    it('creates an interview with all fields', function () {
        $application = JobApplication::factory()->create();

        $interview = Interview::factory()->create([
            'job_application_id' => $application->id,
            'type' => InterviewType::VideoInterview,
            'status' => InterviewStatus::Scheduled,
            'title' => 'Technical Interview',
            'duration_minutes' => 60,
            'meeting_url' => 'https://meet.example.com/abc',
        ]);

        expect($interview->type)->toBe(InterviewType::VideoInterview);
        expect($interview->status)->toBe(InterviewStatus::Scheduled);
        expect($interview->title)->toBe('Technical Interview');
        expect($interview->jobApplication->id)->toBe($application->id);
    });

    it('has panelists relationship', function () {
        $interview = Interview::factory()->create();
        InterviewPanelist::factory()->count(3)->create([
            'interview_id' => $interview->id,
        ]);

        expect($interview->panelists)->toHaveCount(3);
    });

    it('belongs to a job application', function () {
        $application = JobApplication::factory()->create();
        $interview = Interview::factory()->create([
            'job_application_id' => $application->id,
        ]);

        expect($interview->jobApplication->id)->toBe($application->id);
    });

    it('is accessible from job application', function () {
        $application = JobApplication::factory()->create();
        Interview::factory()->count(2)->create([
            'job_application_id' => $application->id,
        ]);

        expect($application->interviews)->toHaveCount(2);
    });
});

describe('InterviewPanelist Model', function () {
    it('creates a panelist with feedback', function () {
        $panelist = InterviewPanelist::factory()->withFeedback()->create();

        expect($panelist->feedback)->not->toBeNull();
        expect($panelist->rating)->toBeGreaterThanOrEqual(1);
        expect($panelist->rating)->toBeLessThanOrEqual(5);
        expect($panelist->feedback_submitted_at)->not->toBeNull();
    });

    it('creates a lead panelist', function () {
        $panelist = InterviewPanelist::factory()->lead()->create();

        expect($panelist->is_lead)->toBeTrue();
    });

    it('enforces unique interview-employee constraint', function () {
        $interview = Interview::factory()->create();
        $employee = Employee::factory()->create();

        InterviewPanelist::factory()->create([
            'interview_id' => $interview->id,
            'employee_id' => $employee->id,
        ]);

        expect(fn () => InterviewPanelist::factory()->create([
            'interview_id' => $interview->id,
            'employee_id' => $employee->id,
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

describe('InterviewService', function () {
    it('creates an interview with panelists', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->create();
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        $service = new InterviewService;
        $interview = $service->create($application, [
            'type' => InterviewType::PanelInterview->value,
            'title' => 'Panel Round',
            'scheduled_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'duration_minutes' => 90,
            'panelist_ids' => [$employee1->id, $employee2->id],
            'lead_panelist_id' => $employee1->id,
        ]);

        expect($interview->status)->toBe(InterviewStatus::Scheduled);
        expect($interview->panelists)->toHaveCount(2);
        expect($interview->panelists->where('is_lead', true)->first()->employee_id)->toBe($employee1->id);
    });

    it('creates an interview without panelists', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $application = JobApplication::factory()->create();

        $service = new InterviewService;
        $interview = $service->create($application, [
            'type' => InterviewType::PhoneScreen->value,
            'title' => 'Initial Screen',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
        ]);

        expect($interview->title)->toBe('Initial Screen');
        expect($interview->panelists)->toHaveCount(0);
    });

    it('updates an interview', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $interview = Interview::factory()->create([
            'title' => 'Old Title',
            'duration_minutes' => 60,
        ]);

        $service = new InterviewService;
        $updated = $service->update($interview, [
            'title' => 'Updated Title',
            'duration_minutes' => 90,
        ]);

        expect($updated->title)->toBe('Updated Title');
        expect($updated->duration_minutes)->toBe(90);
    });

    it('prevents updating a completed interview', function () {
        $interview = Interview::factory()->withStatus(InterviewStatus::Completed)->create();

        $service = new InterviewService;

        expect(fn () => $service->update($interview, ['title' => 'New']))
            ->toThrow(ValidationException::class);
    });

    it('prevents updating a cancelled interview', function () {
        $interview = Interview::factory()->withStatus(InterviewStatus::Cancelled)->create();

        $service = new InterviewService;

        expect(fn () => $service->update($interview, ['title' => 'New']))
            ->toThrow(ValidationException::class);
    });

    it('cancels an interview', function () {
        $interview = Interview::factory()->create();

        $service = new InterviewService;
        $cancelled = $service->cancel($interview, 'Schedule conflict');

        expect($cancelled->status)->toBe(InterviewStatus::Cancelled);
        expect($cancelled->cancellation_reason)->toBe('Schedule conflict');
        expect($cancelled->cancelled_at)->not->toBeNull();
    });

    it('prevents cancelling a completed interview', function () {
        $interview = Interview::factory()->withStatus(InterviewStatus::Completed)->create();

        $service = new InterviewService;

        expect(fn () => $service->cancel($interview, 'Too late'))
            ->toThrow(ValidationException::class);
    });

    it('submits panelist feedback', function () {
        $panelist = InterviewPanelist::factory()->create();

        $service = new InterviewService;
        $updated = $service->submitFeedback($panelist, 'Great candidate, strong technical skills', 4);

        expect($updated->feedback)->toBe('Great candidate, strong technical skills');
        expect($updated->rating)->toBe(4);
        expect($updated->feedback_submitted_at)->not->toBeNull();
    });

    it('marks invitations as sent', function () {
        $interview = Interview::factory()->create();
        InterviewPanelist::factory()->count(2)->create([
            'interview_id' => $interview->id,
        ]);

        $service = new InterviewService;
        $service->markInvitationsSent($interview);

        $interview->refresh();
        foreach ($interview->panelists as $panelist) {
            expect($panelist->invitation_sent_at)->not->toBeNull();
        }
    });

    it('syncs panelists on update', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $interview = Interview::factory()->create();
        $emp1 = Employee::factory()->create();
        $emp2 = Employee::factory()->create();
        $emp3 = Employee::factory()->create();

        InterviewPanelist::factory()->create([
            'interview_id' => $interview->id,
            'employee_id' => $emp1->id,
        ]);

        $service = new InterviewService;
        $updated = $service->update($interview, [
            'panelist_ids' => [$emp2->id, $emp3->id],
            'lead_panelist_id' => $emp2->id,
        ]);

        expect($updated->panelists)->toHaveCount(2);
        expect($updated->panelists->pluck('employee_id')->toArray())->toContain($emp2->id, $emp3->id);
        expect($updated->panelists->pluck('employee_id')->toArray())->not->toContain($emp1->id);
    });
});

describe('InterviewCalendarService', function () {
    it('generates valid ical content', function () {
        $interview = Interview::factory()->create([
            'title' => 'Panel Interview',
            'meeting_url' => 'https://meet.example.com/test',
            'duration_minutes' => 60,
        ]);

        $service = new InterviewCalendarService;
        $ics = $service->generateIcs($interview);

        expect($ics)->toContain('BEGIN:VCALENDAR');
        expect($ics)->toContain('BEGIN:VEVENT');
        expect($ics)->toContain('Panel Interview');
        expect($ics)->toContain('meet.example.com');
        expect($ics)->toContain('END:VEVENT');
        expect($ics)->toContain('END:VCALENDAR');
    });

    it('includes location when set', function () {
        $interview = Interview::factory()->create([
            'location' => 'Conference Room A',
        ]);

        $service = new InterviewCalendarService;
        $ics = $service->generateIcs($interview);

        expect($ics)->toContain('LOCATION:Conference Room A');
    });

    it('includes attendees for panelists', function () {
        $interview = Interview::factory()->create();
        InterviewPanelist::factory()->count(2)->create([
            'interview_id' => $interview->id,
        ]);

        $service = new InterviewCalendarService;
        $ics = $service->generateIcs($interview);

        expect($ics)->toContain('ATTENDEE');
    });
});

describe('Interview Enums', function () {
    it('has correct InterviewType labels', function () {
        expect(InterviewType::PhoneScreen->label())->toBe('Phone Screen');
        expect(InterviewType::VideoInterview->label())->toBe('Video Interview');
        expect(InterviewType::InPerson->label())->toBe('In-Person');
        expect(InterviewType::PanelInterview->label())->toBe('Panel Interview');
        expect(InterviewType::TechnicalAssessment->label())->toBe('Technical Assessment');
    });

    it('has correct InterviewStatus labels', function () {
        expect(InterviewStatus::Scheduled->label())->toBe('Scheduled');
        expect(InterviewStatus::Confirmed->label())->toBe('Confirmed');
        expect(InterviewStatus::InProgress->label())->toBe('In Progress');
        expect(InterviewStatus::Completed->label())->toBe('Completed');
        expect(InterviewStatus::Cancelled->label())->toBe('Cancelled');
        expect(InterviewStatus::NoShow->label())->toBe('No Show');
    });

    it('identifies terminal statuses correctly', function () {
        expect(InterviewStatus::Completed->isTerminal())->toBeTrue();
        expect(InterviewStatus::Cancelled->isTerminal())->toBeTrue();
        expect(InterviewStatus::NoShow->isTerminal())->toBeTrue();
        expect(InterviewStatus::Scheduled->isTerminal())->toBeFalse();
        expect(InterviewStatus::Confirmed->isTerminal())->toBeFalse();
    });

    it('generates options for select components', function () {
        $typeOptions = InterviewType::options();
        expect($typeOptions)->toHaveCount(5);
        expect($typeOptions[0])->toHaveKeys(['value', 'label', 'color']);

        $statusOptions = InterviewStatus::options();
        expect($statusOptions)->toHaveCount(6);
    });
});
