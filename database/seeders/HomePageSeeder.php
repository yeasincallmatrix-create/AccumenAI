<?php

namespace Database\Seeders;

use App\Models\HomePage;
use Illuminate\Database\Seeder;

class HomePageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'name'           => 'Default Landing',
                'slug'           => 'default',
                'description'    => 'General-purpose landing page for all industries.',
                'hero_title'     => 'The Future of Business & Education Management',
                'hero_subtitle'  => 'AccumenAI unifies CRM, HR, Finance, Inventory, Academics and AI assistance into one powerful, tenant-isolated platform. Built for every industry, from schools to hospitals.',
                'hero_badge'     => 'Trusted by 500+ Institutes Worldwide',
                'hero_cta_text'  => 'Get Started Free',
                'hero_cta_url'   => null,
                'is_active'      => true,
                'is_global'      => true,
            ],
            [
                'name'           => 'Education Focus',
                'slug'           => 'education',
                'description'    => 'Education-focused landing page for schools, colleges & universities.',
                'hero_title'     => 'The Future of Education Management',
                'hero_subtitle'  => 'Complete academic management — admissions, attendance, exams, grading, fees, certificates — all in one powerful platform.',
                'hero_badge'     => 'Trusted by 200+ Schools & Colleges',
                'hero_cta_text'  => 'Start Free Trial',
                'hero_cta_url'   => null,
                'is_active'      => true,
                'is_global'      => false,
            ],
            [
                'name'           => 'Healthcare Focus',
                'slug'           => 'healthcare',
                'description'    => 'Healthcare-focused landing page for hospitals, clinics & medical facilities.',
                'hero_title'     => 'The Future of Healthcare Management',
                'hero_subtitle'  => 'Patient management, pharmacy, OPD/IPD, lab reports, billing, and DGDA compliance — all unified in one platform.',
                'hero_badge'     => 'Trusted by 100+ Healthcare Facilities',
                'hero_cta_text'  => 'Start Free Trial',
                'hero_cta_url'   => null,
                'is_active'      => true,
                'is_global'      => false,
            ],
            [
                'name'           => 'Business & Enterprise',
                'slug'           => 'business',
                'description'    => 'Corporate landing page for businesses, trading & enterprises.',
                'hero_title'     => 'The Future of Business Management',
                'hero_subtitle'  => 'CRM, Sales, Purchase, Finance, HR & Payroll — a complete business management suite in one platform.',
                'hero_badge'     => 'Trusted by 300+ Businesses',
                'hero_cta_text'  => 'Get Started Free',
                'hero_cta_url'   => null,
                'is_active'      => true,
                'is_global'      => false,
            ],
            [
                'name'           => 'Minimal Clean',
                'slug'           => 'minimal',
                'description'    => 'Minimal, clean landing page with no clutter.',
                'hero_title'     => 'Simple, Powerful Management Platform',
                'hero_subtitle'  => 'Everything you need to run your organization — in one place.',
                'hero_badge'     => 'Free for small teams',
                'hero_cta_text'  => 'Get Started',
                'hero_cta_url'   => null,
                'is_active'      => false,
                'is_global'      => false,
            ],
        ];

        foreach ($pages as $data) {
            HomePage::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
