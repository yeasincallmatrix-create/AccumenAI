<?php

namespace Tests\Feature;

use App\Support\InstituteDomain;
use Tests\TestCase;

class IndustryInstitutionDomainTest extends TestCase
{
    public function test_education_exists(): void
    {
        $this->assertArrayHasKey('education', config('industry_rules.global.industries'));
    }

    public function test_training_center_exists_independently(): void
    {
        $this->assertArrayHasKey('training_center', config('industry_rules.global.industries'));
        $this->assertNotEmpty(config('industry_rules.global.sub_industries.training_center'));
    }

    public function test_training_center_is_not_child_of_education(): void
    {
        $eduSubs = array_keys(config('industry_rules.global.sub_industries.education', []));
        $this->assertNotContains('training_institute', $eduSubs);
        $this->assertNotContains('professional_training_center', $eduSubs);
        $this->assertNotContains('dance_academy', $eduSubs);
        $this->assertNotContains('it_training_center', $eduSubs);
        $this->assertNotContains('vocational_training_center', $eduSubs);
    }

    public function test_school_maps_academic(): void { $this->assertSame('academic', InstituteDomain::fromKeys('education', 'school')); }
    public function test_college_maps_academic(): void { $this->assertSame('academic', InstituteDomain::fromKeys('education','college')); }
    public function test_polytechnic_maps_academic(): void { $this->assertSame('academic', InstituteDomain::fromKeys('education','polytechnic')); }
    public function test_university_maps_academic(): void { $this->assertSame('academic', InstituteDomain::fromKeys('education','university')); }

    public function test_training_institute_maps_professional(): void { $this->assertSame('professional', InstituteDomain::fromKeys('training_center','training_institute')); }
    public function test_professional_training_center_maps_professional(): void { $this->assertSame('professional', InstituteDomain::fromKeys('training_center','professional_training_center')); }
    public function test_dance_academy_maps_professional(): void { $this->assertSame('professional', InstituteDomain::fromKeys('training_center','dance_academy')); }
    public function test_it_training_center_maps_professional(): void { $this->assertSame('professional', InstituteDomain::fromKeys('training_center','it_training_center')); }
    public function test_vocational_training_center_maps_professional(): void { $this->assertSame('professional', InstituteDomain::fromKeys('training_center','vocational_training_center')); }

    public function test_service_and_transportation_exist(): void
    {
        $this->assertArrayHasKey('service', config('industry_rules.global.industries'));
        $this->assertArrayHasKey('transportation', config('industry_rules.global.industries'));
        $this->assertArrayHasKey('polytechnic', config('industry_rules.global.sub_industries.education'));
    }

    public function test_legacy_aliases_normalize(): void
    {
        // legacy education/institution is now OTHER (ambiguous) — canonical is training_center/training_institute
        $this->assertSame('other', InstituteDomain::fromKeys('education','institution'));
        $this->assertSame('professional', InstituteDomain::fromKeys('training_center','training_institute'));
        $this->assertSame('transportation', InstituteDomain::normalizeIndustry('transport'));
    }

    public function test_subject_type_derived_for_academic(): void
    {
        $mock = new \App\Models\Institute(['industry'=>'education','sub_industry'=>'school']);
        $this->assertSame('academic', InstituteDomain::subjectTypeFor($mock));
    }

    public function test_subject_type_derived_for_professional(): void
    {
        $mock = new \App\Models\Institute(['industry'=>'training_center','sub_industry'=>'dance_academy']);
        $this->assertSame('professional', InstituteDomain::subjectTypeFor($mock));
    }
}
