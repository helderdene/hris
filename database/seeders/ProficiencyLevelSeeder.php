<?php

namespace Database\Seeders;

use App\Models\ProficiencyLevel;
use Illuminate\Database\Seeder;

class ProficiencyLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the standard 1-5 proficiency level scale used for
     * competency evaluations throughout the organization.
     */
    public function run(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'Novice',
                'description' => 'Limited or no experience with this competency. Requires close supervision and guidance. Still learning fundamental concepts and practices.',
                'behavioral_indicators' => [
                    'Needs detailed instructions for tasks',
                    'Requires frequent check-ins and feedback',
                    'Still developing basic understanding',
                    'May struggle with unexpected situations',
                ],
            ],
            [
                'level' => 2,
                'name' => 'Beginner',
                'description' => 'Basic understanding of the competency. Can perform routine tasks with some guidance. Building confidence through practice.',
                'behavioral_indicators' => [
                    'Handles routine tasks with guidance',
                    'Asks appropriate questions when stuck',
                    'Shows improvement over time',
                    'Can follow established procedures',
                ],
            ],
            [
                'level' => 3,
                'name' => 'Competent',
                'description' => 'Solid working knowledge. Can independently handle standard situations. Reliable performance on typical tasks.',
                'behavioral_indicators' => [
                    'Works independently on standard tasks',
                    'Solves typical problems without assistance',
                    'Consistently meets expectations',
                    'Applies knowledge across similar situations',
                ],
            ],
            [
                'level' => 4,
                'name' => 'Proficient',
                'description' => 'Advanced understanding and application. Handles complex situations effectively. Can mentor and guide others.',
                'behavioral_indicators' => [
                    'Handles complex or unusual situations',
                    'Mentors and coaches others',
                    'Improves processes and approaches',
                    'Recognized as a go-to resource',
                ],
            ],
            [
                'level' => 5,
                'name' => 'Expert',
                'description' => 'Deep expertise and mastery. Drives innovation and best practices. Recognized authority in this area.',
                'behavioral_indicators' => [
                    'Drives innovation and thought leadership',
                    'Develops new methods or approaches',
                    'Influences organizational strategy',
                    'Sought out as an authority on the topic',
                ],
            ],
        ];

        foreach ($levels as $level) {
            ProficiencyLevel::updateOrCreate(
                ['level' => $level['level']],
                $level
            );
        }
    }
}
