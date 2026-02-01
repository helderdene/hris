<?php

namespace App\Http\Controllers\Organization;

use App\Enums\CompetencyCategory;
use App\Enums\JobLevel;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetencyResource;
use App\Http\Resources\ProficiencyLevelResource;
use App\Models\Competency;
use App\Models\ProficiencyLevel;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyPageController extends Controller
{
    /**
     * Display the competency management page.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all competencies with position assignment counts
        $competencies = Competency::query()
            ->withCount('positionCompetencies')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Get proficiency levels for reference
        $proficiencyLevels = ProficiencyLevel::ordered();

        // Get unique categories from competencies
        $categories = $this->getCategoryOptions();

        // Get job level options
        $jobLevels = $this->getJobLevelOptions();

        return Inertia::render('Organization/Competencies/Index', [
            'competencies' => CompetencyResource::collection($competencies),
            'proficiencyLevels' => ProficiencyLevelResource::collection($proficiencyLevels),
            'categories' => $categories,
            'jobLevels' => $jobLevels,
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
