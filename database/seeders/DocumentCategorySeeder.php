<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

/**
 * Seeder for predefined document categories.
 *
 * Seeds the default categories that are available in all tenant databases.
 * These categories are marked as is_predefined = true and cannot be deleted.
 */
class DocumentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $predefinedCategories = [
            [
                'name' => 'Contracts',
                'description' => 'Employment contracts, agreements, and amendments',
                'is_predefined' => true,
            ],
            [
                'name' => 'Certifications',
                'description' => 'Professional certifications, licenses, and credentials',
                'is_predefined' => true,
            ],
            [
                'name' => 'Personal Documents',
                'description' => 'Government IDs, personal identification, and related documents',
                'is_predefined' => true,
            ],
            [
                'name' => 'Company Memos',
                'description' => 'Internal memos, announcements, and official communications',
                'is_predefined' => true,
            ],
            [
                'name' => 'Profile Photo',
                'description' => 'Employee profile photos for biometric device synchronization',
                'is_predefined' => true,
            ],
        ];

        foreach ($predefinedCategories as $category) {
            DocumentCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
