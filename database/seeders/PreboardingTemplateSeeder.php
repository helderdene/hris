<?php

namespace Database\Seeders;

use App\Enums\PreboardingItemType;
use App\Models\PreboardingTemplate;
use App\Models\PreboardingTemplateItem;
use Illuminate\Database\Seeder;

class PreboardingTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $template = PreboardingTemplate::firstOrCreate(
            ['name' => 'Default PH Pre-boarding Template'],
            [
                'description' => 'Standard pre-employment requirements checklist for Philippine-based new hires.',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $items = [
            ['type' => PreboardingItemType::DocumentUpload, 'name' => 'Valid Government ID', 'description' => 'Upload a clear copy of any valid government-issued ID (passport, driver\'s license, SSS ID, UMID, etc.).', 'is_required' => true, 'sort_order' => 1],
            ['type' => PreboardingItemType::DocumentUpload, 'name' => 'Signed Employment Contract', 'description' => 'Upload the signed copy of your employment contract.', 'is_required' => true, 'sort_order' => 2],
            ['type' => PreboardingItemType::FormField, 'name' => 'BIR TIN Number', 'description' => 'Enter your Bureau of Internal Revenue Tax Identification Number.', 'is_required' => true, 'sort_order' => 3],
            ['type' => PreboardingItemType::FormField, 'name' => 'SSS Number', 'description' => 'Enter your Social Security System number.', 'is_required' => true, 'sort_order' => 4],
            ['type' => PreboardingItemType::FormField, 'name' => 'PhilHealth Number', 'description' => 'Enter your PhilHealth member number.', 'is_required' => true, 'sort_order' => 5],
            ['type' => PreboardingItemType::FormField, 'name' => 'Pag-IBIG Number', 'description' => 'Enter your Pag-IBIG MID number.', 'is_required' => true, 'sort_order' => 6],
            ['type' => PreboardingItemType::FormField, 'name' => 'Bank Account Details', 'description' => 'Enter your bank name, account number, and branch for payroll processing.', 'is_required' => true, 'sort_order' => 7],
            ['type' => PreboardingItemType::FormField, 'name' => 'Emergency Contact Information', 'description' => 'Enter your emergency contact\'s name, relationship, and phone number.', 'is_required' => true, 'sort_order' => 8],
            ['type' => PreboardingItemType::DocumentUpload, 'name' => 'Health Certificate', 'description' => 'Upload your health certificate if available.', 'is_required' => false, 'sort_order' => 9],
            ['type' => PreboardingItemType::DocumentUpload, 'name' => 'NBI/Police Clearance', 'description' => 'Upload your NBI or Police Clearance document.', 'is_required' => false, 'sort_order' => 10],
            ['type' => PreboardingItemType::DocumentUpload, 'name' => 'Pre-employment Medical Results', 'description' => 'Upload your pre-employment medical examination results.', 'is_required' => false, 'sort_order' => 11],
            ['type' => PreboardingItemType::Acknowledgment, 'name' => 'Company Policy Acknowledgment', 'description' => 'I acknowledge that I have read and understood the company policies and employee handbook.', 'is_required' => true, 'sort_order' => 12],
        ];

        foreach ($items as $itemData) {
            PreboardingTemplateItem::firstOrCreate(
                [
                    'preboarding_template_id' => $template->id,
                    'name' => $itemData['name'],
                ],
                array_merge($itemData, [
                    'preboarding_template_id' => $template->id,
                ])
            );
        }
    }
}
