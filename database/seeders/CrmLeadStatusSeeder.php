<?php

namespace Database\Seeders;

use App\Models\CrmLeadStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared lead-pipeline statuses (slug unique, no institute).
 * 'new' is the default status tests resolve via
 * CrmLeadStatus::where('is_default', true). Idempotent via
 * firstOrCreate. Seeded for tests (B82).
 */
class CrmLeadStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['slug' => CrmLeadStatus::SLUG_NEW, 'name' => 'New', 'is_default' => true, 'display_order' => 1],
            ['slug' => CrmLeadStatus::SLUG_CONTACTED, 'name' => 'Contacted', 'is_default' => false, 'display_order' => 2],
            ['slug' => CrmLeadStatus::SLUG_QUALIFIED, 'name' => 'Qualified', 'is_default' => false, 'display_order' => 3],
            ['slug' => CrmLeadStatus::SLUG_PROPOSAL, 'name' => 'Proposal', 'is_default' => false, 'display_order' => 4],
            ['slug' => CrmLeadStatus::SLUG_WON, 'name' => 'Won', 'is_default' => false, 'display_order' => 5],
            ['slug' => CrmLeadStatus::SLUG_LOST, 'name' => 'Lost', 'is_default' => false, 'display_order' => 6],
        ] as $row) {
            CrmLeadStatus::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'is_default' => $row['is_default'],
                    'display_order' => $row['display_order'],
                    'status' => CrmLeadStatus::STATUS_ACTIVE,
                ]
            );
        }
    }
}
