<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\EducationLevel;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CandidatePageController extends Controller
{
    /**
     * Display the candidates index page.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = Candidate::query()
            ->withCount('jobApplications')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->searchByNameOrEmail($search);
        }

        $candidates = $query->paginate(25)->through(fn (Candidate $candidate) => [
            'id' => $candidate->id,
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'full_name' => $candidate->full_name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'applications_count' => $candidate->job_applications_count,
            'created_at' => $candidate->created_at->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Recruitment/Candidates/Index', [
            'candidates' => $candidates,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display the create candidate page.
     */
    public function create(): Response
    {
        return Inertia::render('Recruitment/Candidates/Create', [
            'educationLevels' => EducationLevel::options(),
        ]);
    }

    /**
     * Display a specific candidate.
     */
    public function show(string $tenant, Candidate $candidate): Response
    {
        $candidate->load([
            'education',
            'workExperiences',
            'jobApplications.jobPosting',
            'jobApplications.assessments',
            'jobApplications.backgroundChecks',
            'jobApplications.referenceChecks',
        ]);

        return Inertia::render('Recruitment/Candidates/Show', [
            'candidate' => [
                'id' => $candidate->id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'full_name' => $candidate->full_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'date_of_birth' => $candidate->date_of_birth?->format('Y-m-d'),
                'address' => $candidate->address,
                'city' => $candidate->city,
                'state' => $candidate->state,
                'zip_code' => $candidate->zip_code,
                'country' => $candidate->country,
                'linkedin_url' => $candidate->linkedin_url,
                'portfolio_url' => $candidate->portfolio_url,
                'resume_file_name' => $candidate->resume_file_name,
                'skills' => $candidate->skills,
                'notes' => $candidate->notes,
                'education' => $candidate->education->map(fn ($edu) => [
                    'id' => $edu->id,
                    'education_level' => $edu->education_level->value,
                    'education_level_label' => $edu->education_level->label(),
                    'institution' => $edu->institution,
                    'field_of_study' => $edu->field_of_study,
                    'start_date' => $edu->start_date?->format('Y-m-d'),
                    'end_date' => $edu->end_date?->format('Y-m-d'),
                    'is_current' => $edu->is_current,
                ]),
                'work_experiences' => $candidate->workExperiences->map(fn ($exp) => [
                    'id' => $exp->id,
                    'company' => $exp->company,
                    'job_title' => $exp->job_title,
                    'description' => $exp->description,
                    'start_date' => $exp->start_date?->format('Y-m-d'),
                    'end_date' => $exp->end_date?->format('Y-m-d'),
                    'is_current' => $exp->is_current,
                ]),
                'job_applications' => $candidate->jobApplications->map(fn ($app) => [
                    'id' => $app->id,
                    'job_posting' => [
                        'id' => $app->jobPosting->id,
                        'title' => $app->jobPosting->title,
                    ],
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
                    'status_color' => $app->status->color(),
                    'source_label' => $app->source->label(),
                    'applied_at' => $app->applied_at?->format('Y-m-d H:i:s'),
                ]),
                'created_at' => $candidate->created_at->format('Y-m-d H:i:s'),
                'assessments_count' => $candidate->jobApplications->sum(fn ($app) => $app->assessments->count()),
                'background_checks_count' => $candidate->jobApplications->sum(fn ($app) => $app->backgroundChecks->count()),
                'reference_checks_count' => $candidate->jobApplications->sum(fn ($app) => $app->referenceChecks->count()),
            ],
            'statuses' => ApplicationStatus::options(),
        ]);
    }

    /**
     * Display the edit candidate page.
     */
    public function edit(string $tenant, Candidate $candidate): Response
    {
        $candidate->load(['education', 'workExperiences']);

        return Inertia::render('Recruitment/Candidates/Edit', [
            'candidate' => [
                'id' => $candidate->id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'date_of_birth' => $candidate->date_of_birth?->format('Y-m-d'),
                'address' => $candidate->address,
                'city' => $candidate->city,
                'state' => $candidate->state,
                'zip_code' => $candidate->zip_code,
                'country' => $candidate->country,
                'linkedin_url' => $candidate->linkedin_url,
                'portfolio_url' => $candidate->portfolio_url,
                'resume_file_name' => $candidate->resume_file_name,
                'skills' => $candidate->skills ?? [],
                'notes' => $candidate->notes,
                'education' => $candidate->education->map(fn ($edu) => [
                    'id' => $edu->id,
                    'education_level' => $edu->education_level->value,
                    'institution' => $edu->institution,
                    'field_of_study' => $edu->field_of_study,
                    'start_date' => $edu->start_date?->format('Y-m-d'),
                    'end_date' => $edu->end_date?->format('Y-m-d'),
                    'is_current' => $edu->is_current,
                ]),
                'work_experiences' => $candidate->workExperiences->map(fn ($exp) => [
                    'id' => $exp->id,
                    'company' => $exp->company,
                    'job_title' => $exp->job_title,
                    'description' => $exp->description,
                    'start_date' => $exp->start_date?->format('Y-m-d'),
                    'end_date' => $exp->end_date?->format('Y-m-d'),
                    'is_current' => $exp->is_current,
                ]),
            ],
            'educationLevels' => EducationLevel::options(),
        ]);
    }
}
