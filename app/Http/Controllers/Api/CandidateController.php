<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Http\Resources\CandidateListResource;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use App\Services\Recruitment\DuplicateDetectionService;
use App\Services\Recruitment\ResumeParsingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CandidateController extends Controller
{
    public function __construct(
        protected ResumeParsingService $resumeParser,
        protected DuplicateDetectionService $duplicateDetector
    ) {}

    /**
     * Display a listing of candidates.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Candidate::query()
            ->withCount('jobApplications')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->searchByNameOrEmail($request->input('search'));
        }

        return CandidateListResource::collection($query->paginate(25));
    }

    /**
     * Store a newly created candidate.
     */
    public function store(StoreCandidateRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $candidate = DB::transaction(function () use ($request) {
            $data = $request->validatedWithDefaults();
            $education = $data['education'] ?? [];
            $workExperience = $data['work_experience'] ?? [];
            unset($data['education'], $data['work_experience'], $data['resume']);

            if ($request->hasFile('resume')) {
                $file = $request->file('resume');
                $data['resume_file_path'] = $file->store('resumes', 'local');
                $data['resume_file_name'] = $file->getClientOriginalName();
                $data['resume_parsed_text'] = $this->resumeParser->parseFile($file);
            }

            $candidate = Candidate::create($data);

            foreach ($education as $edu) {
                $candidate->education()->create($edu);
            }

            foreach ($workExperience as $exp) {
                $candidate->workExperiences()->create($exp);
            }

            return $candidate;
        });

        $candidate->load(['education', 'workExperiences']);

        return (new CandidateResource($candidate))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified candidate.
     */
    public function show(Candidate $candidate): CandidateResource
    {
        $candidate->load(['education', 'workExperiences', 'jobApplications.jobPosting']);

        return new CandidateResource($candidate);
    }

    /**
     * Update the specified candidate.
     */
    public function update(UpdateCandidateRequest $request, Candidate $candidate): CandidateResource
    {
        Gate::authorize('can-manage-organization');

        DB::transaction(function () use ($request, $candidate) {
            $data = $request->validated();
            $education = $data['education'] ?? null;
            $workExperience = $data['work_experience'] ?? null;
            unset($data['education'], $data['work_experience'], $data['resume']);

            if ($request->hasFile('resume')) {
                $file = $request->file('resume');
                $data['resume_file_path'] = $file->store('resumes', 'local');
                $data['resume_file_name'] = $file->getClientOriginalName();
                $data['resume_parsed_text'] = $this->resumeParser->parseFile($file);
            }

            $candidate->update($data);

            if ($education !== null) {
                $candidate->education()->delete();
                foreach ($education as $edu) {
                    $candidate->education()->create($edu);
                }
            }

            if ($workExperience !== null) {
                $candidate->workExperiences()->delete();
                foreach ($workExperience as $exp) {
                    $candidate->workExperiences()->create($exp);
                }
            }
        });

        $candidate->load(['education', 'workExperiences']);

        return new CandidateResource($candidate);
    }

    /**
     * Remove the specified candidate.
     */
    public function destroy(Candidate $candidate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $candidate->delete();

        return response()->json(['message' => 'Candidate deleted successfully.']);
    }

    /**
     * Check for duplicate candidates.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $results = $this->duplicateDetector->findDuplicates(
            $request->input('email'),
            $request->input('phone'),
            $request->input('first_name'),
            $request->input('last_name'),
            $request->input('exclude_id')
        );

        return response()->json([
            'exact' => CandidateListResource::collection($results['exact']),
            'potential' => CandidateListResource::collection($results['potential']),
            'has_duplicates' => $results['exact']->isNotEmpty() || $results['potential']->isNotEmpty(),
        ]);
    }
}
