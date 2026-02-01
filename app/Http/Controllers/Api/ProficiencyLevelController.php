<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProficiencyLevelResource;
use App\Models\ProficiencyLevel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProficiencyLevelController extends Controller
{
    /**
     * Display a listing of proficiency levels.
     *
     * Returns all 5 proficiency levels ordered by level number.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $levels = ProficiencyLevel::ordered();

        return ProficiencyLevelResource::collection($levels);
    }

    /**
     * Display the specified proficiency level.
     */
    public function show(string $tenant, ProficiencyLevel $proficiencyLevel): ProficiencyLevelResource
    {
        Gate::authorize('can-manage-organization');

        return new ProficiencyLevelResource($proficiencyLevel);
    }
}
