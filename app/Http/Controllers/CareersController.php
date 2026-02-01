<?php

namespace App\Http\Controllers;

use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CareersController extends Controller
{
    /**
     * Display the public careers page.
     */
    public function index(Request $request): Response
    {
        $departmentId = $request->input('department_id');
        $search = $request->input('search');

        $query = JobPosting::query()
            ->publiclyVisible()
            ->with(['department'])
            ->orderBy('published_at', 'desc');

        if ($departmentId) {
            $query->forDepartment((int) $departmentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $postings = $query->paginate(12)->through(fn (JobPosting $posting) => [
            'id' => $posting->id,
            'slug' => $posting->slug,
            'title' => $posting->title,
            'department' => [
                'id' => $posting->department->id,
                'name' => $posting->department->name,
            ],
            'employment_type_label' => $posting->employment_type->label(),
            'location' => $posting->location,
            'salary_display' => $this->formatSalaryDisplay($posting),
            'published_at' => $posting->published_at?->format('M d, Y'),
        ]);

        $departments = Department::query()
            ->whereHas('jobPostings', fn ($q) => $q->publiclyVisible())
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ]);

        return Inertia::render('Careers/Index', [
            'postings' => $postings,
            'departments' => $departments,
            'filters' => [
                'department_id' => $departmentId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display a single public job posting.
     */
    public function show(string $tenant, string $slug): Response
    {
        $posting = JobPosting::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->with(['department'])
            ->firstOrFail();

        return Inertia::render('Careers/Show', [
            'posting' => [
                'id' => $posting->id,
                'slug' => $posting->slug,
                'title' => $posting->title,
                'department' => [
                    'id' => $posting->department->id,
                    'name' => $posting->department->name,
                ],
                'description' => $posting->description,
                'requirements' => $posting->requirements,
                'benefits' => $posting->benefits,
                'employment_type_label' => $posting->employment_type->label(),
                'location' => $posting->location,
                'salary_display' => $this->formatSalaryDisplay($posting),
                'application_instructions' => $posting->application_instructions,
                'published_at' => $posting->published_at?->format('M d, Y'),
            ],
        ]);
    }

    /**
     * Format salary display based on the display option.
     */
    private function formatSalaryDisplay(JobPosting $posting): ?string
    {
        return match ($posting->salary_display_option) {
            SalaryDisplayOption::ExactRange => $posting->salary_range_min && $posting->salary_range_max
                ? number_format((float) $posting->salary_range_min).' - '.number_format((float) $posting->salary_range_max)
                : null,
            SalaryDisplayOption::RangeOnly => $posting->salary_range_min && $posting->salary_range_max
                ? number_format((float) $posting->salary_range_min, 0, '.', ',').' - '.number_format((float) $posting->salary_range_max, 0, '.', ',')
                : null,
            SalaryDisplayOption::Negotiable => 'Negotiable',
            SalaryDisplayOption::Hidden, null => null,
        };
    }
}
