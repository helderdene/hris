<?php

namespace App\Http\Controllers\Organization;

use App\Enums\CompetencyCategory;
use App\Enums\JobLevel;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetencyResource;
use App\Http\Resources\PositionCompetencyResource;
use App\Http\Resources\PositionResource;
use App\Http\Resources\ProficiencyLevelResource;
use App\Models\Competency;
use App\Models\Position;
use App\Models\PositionCompetency;
use App\Models\ProficiencyLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyMatrixPageController extends Controller
{
    /**
     * Display the competency matrix page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all active positions
        $positions = Position::query()
            ->active()
            ->orderBy('title')
            ->get();

        // Get all active competencies
        $competencies = Competency::query()
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Get position competencies with filtering
        $matrixQuery = PositionCompetency::query()
            ->with(['position', 'competency', 'proficiencyLevel'])
            ->withActiveCompetency();

        // Filter by position
        if ($request->filled('position_id')) {
            $matrixQuery->forPosition($request->integer('position_id'));
        }

        // Filter by job level
        if ($request->filled('job_level')) {
            $jobLevel = JobLevel::tryFrom($request->input('job_level'));
            if ($jobLevel) {
                $matrixQuery->forJobLevel($jobLevel);
            }
        }

        // Filter by competency category
        if ($request->filled('category')) {
            $category = CompetencyCategory::tryFrom($request->input('category'));
            if ($category) {
                $matrixQuery->whereHas('competency', function ($q) use ($category) {
                    $q->where('category', $category->value);
                });
            }
        }

        $positionCompetencies = $matrixQuery
            ->orderBy('position_id')
            ->orderBy('job_level')
            ->get();

        // Get proficiency levels for reference
        $proficiencyLevels = ProficiencyLevel::ordered();

        // Get category and job level options
        $categories = $this->getCategoryOptions();
        $jobLevels = $this->getJobLevelOptions();

        return Inertia::render('Organization/CompetencyMatrix/Index', [
            'positions' => PositionResource::collection($positions),
            'competencies' => CompetencyResource::collection($competencies),
            'positionCompetencies' => PositionCompetencyResource::collection($positionCompetencies),
            'proficiencyLevels' => ProficiencyLevelResource::collection($proficiencyLevels),
            'categories' => $categories,
            'jobLevels' => $jobLevels,
            'filters' => [
                'position_id' => $request->input('position_id') ? (int) $request->input('position_id') : null,
                'job_level' => $request->input('job_level'),
                'category' => $request->input('category'),
            ],
        ]);
    }

    /**
     * Get competency category options for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getCategoryOptions(): array
    {
        return array_map(
            fn (CompetencyCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            CompetencyCategory::cases()
        );
    }

    /**
     * Get job level options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getJobLevelOptions(): array
    {
        return array_map(
            fn (JobLevel $level) => [
                'value' => $level->value,
                'label' => $level->label(),
            ],
            JobLevel::cases()
        );
    }
}
