<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MedicalDisplayRenameTest extends TestCase
{
    public function test_medical_row_shows_healthcare_name()
    {
        $row = DB::table('industries')->where('slug', 'medical')->first();
        $this->assertEquals('Healthcare', $row->name);
        $this->assertEquals('active', $row->status);
    }

    public function test_healthcare_duplicate_inactive()
    {
        $row = DB::table('industries')->where('slug', 'healthcare')->first();
        $this->assertEquals('inactive', $row->status);
    }

    public function test_medical_registry_untouched()
    {
        $count = DB::table('module_registry')
            ->where('parent_key', 'medical')
            ->where('status', 'active')
            ->count();
        $this->assertEquals(14, $count);
    }

    public function test_medical_packages_untouched()
    {
        $count = DB::table('subscription_packages')
            ->where('slug', 'LIKE', 'medical_%')
            ->count();
        $this->assertEquals(3, $count);
    }

    public function test_tenants_unchanged()
    {
        $medical = DB::table('institutes')
            ->where('industry', 'medical')
            ->count();
        $this->assertEquals(0, $medical, 'no tenant may be moved to the new key');

        $healthcare = DB::table('institutes')
            ->where('industry', 'healthcare')
            ->count();
        $this->assertGreaterThanOrEqual(2, $healthcare, 'existing healthcare tenants must be preserved');
    }

    public function test_medical_module_key_still_works()
    {
        $exists = DB::table('module_registry')->where('key', 'medical.opd')->exists();
        $this->assertTrue($exists);
    }
}
