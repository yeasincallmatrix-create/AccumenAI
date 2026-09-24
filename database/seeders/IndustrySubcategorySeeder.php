<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustrySubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $subcategories = [
            // ═══ HEALTHCARE ═══
            ['industry_key' => 'healthcare', 'subcategory_key' => 'hospital', 'name' => 'Hospital', 'description' => 'Full-service hospital', 'icon' => 'bi-hospital', 'sort_order' => 1],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'clinic', 'name' => 'Clinic', 'description' => 'Small clinic / doctor chamber', 'icon' => 'bi-clipboard-pulse', 'sort_order' => 2],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'pharmacy', 'name' => 'Pharmacy', 'description' => 'Pharmacy / Drug store', 'icon' => 'bi-capsule', 'sort_order' => 3],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'diagnostic', 'name' => 'Diagnostic Center', 'description' => 'Lab / diagnostic center', 'icon' => 'bi-activity', 'sort_order' => 4],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'dental', 'name' => 'Dental Clinic', 'description' => 'Dental clinic', 'icon' => 'bi-emoji-smile', 'sort_order' => 5],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'physiotherapy', 'name' => 'Physiotherapy Center', 'description' => 'Physiotherapy clinic', 'icon' => 'bi-heart-pulse', 'sort_order' => 6],
            ['industry_key' => 'healthcare', 'subcategory_key' => 'veterinary', 'name' => 'Veterinary Clinic', 'description' => 'Animal clinic', 'icon' => 'bi-bug', 'sort_order' => 7],

            // ═══ EDUCATION ═══
            ['industry_key' => 'education', 'subcategory_key' => 'school', 'name' => 'School', 'description' => 'Primary/Secondary school', 'icon' => 'bi-book', 'sort_order' => 1],
            ['industry_key' => 'education', 'subcategory_key' => 'college', 'name' => 'College', 'description' => 'College / Higher secondary', 'icon' => 'bi-building', 'sort_order' => 2],
            ['industry_key' => 'education', 'subcategory_key' => 'university', 'name' => 'University', 'description' => 'University / Degree college', 'icon' => 'bi-mortarboard', 'sort_order' => 3],
            ['industry_key' => 'education', 'subcategory_key' => 'coaching', 'name' => 'Coaching Center', 'description' => 'Coaching / Tuition center', 'icon' => 'bi-pencil-square', 'sort_order' => 4],
            ['industry_key' => 'education', 'subcategory_key' => 'kindergarten', 'name' => 'Kindergarten', 'description' => 'Kindergarten / Playschool', 'icon' => 'bi-balloon', 'sort_order' => 5],

            // ═══ TRAINING CENTER ═══
            ['industry_key' => 'training_center', 'subcategory_key' => 'vocational', 'name' => 'Vocational Training', 'description' => 'Vocational / skill development', 'icon' => 'bi-tools', 'sort_order' => 1],
            ['industry_key' => 'training_center', 'subcategory_key' => 'it_training', 'name' => 'IT Training', 'description' => 'IT / computer training', 'icon' => 'bi-pc-display', 'sort_order' => 2],
            ['industry_key' => 'training_center', 'subcategory_key' => 'language_center', 'name' => 'Language Center', 'description' => 'Language training', 'icon' => 'bi-translate', 'sort_order' => 3],

            // ═══ RETAIL ═══
            ['industry_key' => 'retail', 'subcategory_key' => 'grocery', 'name' => 'Grocery Store', 'description' => 'Grocery / general store', 'icon' => 'bi-basket', 'sort_order' => 1],
            ['industry_key' => 'retail', 'subcategory_key' => 'electronics', 'name' => 'Electronics Store', 'description' => 'Electronics / mobile shop', 'icon' => 'bi-phone', 'sort_order' => 2],
            ['industry_key' => 'retail', 'subcategory_key' => 'clothing', 'name' => 'Clothing Store', 'description' => 'Apparel / fashion store', 'icon' => 'bi-bag', 'sort_order' => 3],
            ['industry_key' => 'retail', 'subcategory_key' => 'restaurant', 'name' => 'Restaurant', 'description' => 'Restaurant / food service', 'icon' => 'bi-cup-hot', 'sort_order' => 4],

            // ═══ MANUFACTURING ═══
            ['industry_key' => 'manufacturing', 'subcategory_key' => 'general', 'name' => 'General Manufacturing', 'description' => 'General manufacturing', 'icon' => 'bi-gear', 'sort_order' => 1],
            ['industry_key' => 'manufacturing', 'subcategory_key' => 'food_processing', 'name' => 'Food Processing', 'description' => 'Food processing', 'icon' => 'bi-egg-fried', 'sort_order' => 2],

            // ═══ REAL ESTATE ═══
            ['industry_key' => 'real_estate', 'subcategory_key' => 'property', 'name' => 'Property', 'description' => 'Property sales', 'icon' => 'bi-house', 'sort_order' => 1],
            ['industry_key' => 'real_estate', 'subcategory_key' => 'rental', 'name' => 'Rental', 'description' => 'Property rental', 'icon' => 'bi-key', 'sort_order' => 2],
        ];

        foreach ($subcategories as $sub) {
            DB::table('industry_subcategories')->updateOrInsert(
                ['industry_key' => $sub['industry_key'], 'subcategory_key' => $sub['subcategory_key']],
                $sub + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('Industry sub-categories seeded: ' . count($subcategories));
    }
}
