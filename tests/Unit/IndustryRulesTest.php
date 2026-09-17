<?php

namespace Tests\Unit;

use App\Support\IndustryRules;
use Tests\TestCase;

class IndustryRulesTest extends TestCase
{
    public function test_country_with_rules_returns_scoped_industries(): void
    {
        $industries = IndustryRules::industries('Bangladesh');

        // Bangladesh now lists all global industries with country-specific sub-industries.
        // Assert the scoped list matches the Bangladesh config keys (keeps test in sync with config).
        $expected = [];
        foreach (array_keys(config('industry_rules.Bangladesh')) as $slug) {
            if ($slug === 'capabilities') {
                continue;
            }
            $expected[$slug] = config('industry_rules.global.industries.'.$slug) ?? ucwords(str_replace('_', ' ', $slug));
        }
        $this->assertSame($expected, $industries);
        $this->assertArrayHasKey('education', $industries);
        $this->assertArrayHasKey('healthcare', $industries);
    }

    public function test_unknown_country_falls_back_to_global_industries(): void
    {
        $industries = IndustryRules::industries('France');

        $this->assertSame(config('industry_rules.global.industries'), $industries);
        $this->assertArrayHasKey('healthcare', $industries);
    }

    public function test_null_or_empty_country_falls_back_to_global(): void
    {
        $this->assertSame(config('industry_rules.global.industries'), IndustryRules::industries(null));
        $this->assertSame(config('industry_rules.global.industries'), IndustryRules::industries(''));
    }

    public function test_sub_industries_are_scoped_by_country_and_industry(): void
    {
        $subs = IndustryRules::subIndustries('Bangladesh', 'education');

        // Canonical education sub-industries (config/industry_rules.php).
        // Training-center entries (institution, vocational_institute, ...)
        // belong to the independent training_center industry — see
        // IndustryInstitutionDomainTest::test_training_center_is_not_child_of_education.
        $expectedCore = [
            'school' => 'School',
            'college' => 'College',
            'polytechnic' => 'Polytechnic',
            'university' => 'University',
            'madrasha' => 'Madrasha',
            'primary_school' => 'Primary School',
            'secondary_high_school' => 'Secondary / High School',
            'school_college' => 'School & College',
        ];
        foreach ($expectedCore as $k => $v) {
            $this->assertArrayHasKey($k, $subs);
            $this->assertSame($v, $subs[$k]);
        }
        $this->assertGreaterThanOrEqual(count($expectedCore), count($subs));

        // Training-center subs resolve under their own industry, not education.
        $trainingSubs = IndustryRules::subIndustries('Bangladesh', 'training_center');
        $this->assertArrayHasKey('professional_training_center', $trainingSubs);
        $this->assertArrayNotHasKey('professional_training_center', $subs);
    }

    public function test_sub_industries_empty_for_unlisted_industry(): void
    {
        // Bangladesh now has healthcare sub-industries, so use an industry that is still empty in Bangladesh.
        $this->assertSame([], IndustryRules::subIndustries('Bangladesh', 'real_estate'));
        $this->assertSame([], IndustryRules::subIndustries('Bangladesh', 'unknown_industry'));
        $this->assertSame([], IndustryRules::subIndustries('France', 'healthcare'));
    }

    public function test_has_sub_industries(): void
    {
        $this->assertTrue(IndustryRules::hasSubIndustries('Bangladesh', 'education'));
        $this->assertTrue(IndustryRules::hasSubIndustries('Bangladesh', 'healthcare'));
        $this->assertFalse(IndustryRules::hasSubIndustries('Bangladesh', 'real_estate'));
        $this->assertFalse(IndustryRules::hasSubIndustries('France', 'education'));
    }

    public function test_label_resolves_country_scoped_entries(): void
    {
        $this->assertSame('Education', IndustryRules::label('Bangladesh', 'education'));
        $this->assertSame('Madrasha', IndustryRules::label('Bangladesh', 'education', 'madrasha'));
        $this->assertSame('Healthcare', IndustryRules::label('France', 'healthcare'));
    }

    public function test_label_falls_back_to_raw_slugs(): void
    {
        $this->assertSame('mystery', IndustryRules::label('Bangladesh', 'education', 'mystery'));
        $this->assertNull(IndustryRules::label('Bangladesh', 'unknown'));
    }
}