<?php

namespace Database\Seeders;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateWorkExperience;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publishedPostings = JobPosting::query()->published()->get();

        $candidates = Candidate::factory()
            ->count(20)
            ->create();

        foreach ($candidates as $candidate) {
            CandidateEducation::factory()
                ->count(fake()->numberBetween(1, 2))
                ->for($candidate)
                ->create();

            CandidateWorkExperience::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($candidate)
                ->create();
        }

        if ($publishedPostings->isEmpty()) {
            return;
        }

        // Create applications for some candidates
        $applicants = $candidates->random(min(12, $candidates->count()));
        $statuses = [
            ApplicationStatus::Applied,
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Assessment,
            ApplicationStatus::Offer,
            ApplicationStatus::Hired,
            ApplicationStatus::Rejected,
        ];

        foreach ($applicants as $applicant) {
            $posting = $publishedPostings->random();

            JobApplication::factory()
                ->for($applicant, 'candidate')
                ->for($posting, 'jobPosting')
                ->withStatus(fake()->randomElement($statuses))
                ->create([
                    'source' => ApplicationSource::CareersPage,
                ]);
        }
    }
}
