<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GeoLabelsInstituteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_levels_endpoint_works_for_institute_user_under_tenant(): void
    {
        $institute = Institute::create([
            'name' => 'Geo Labels Inst',
            'slug' => 'geo-labels-inst',
            'country' => 'United States',
            'status' => 'active',
        ]);

        $user = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Geo',
            'last_name' => 'User',
            'email' => 'geo-labels@example.test',
            'phone' => '12345678',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $au = Country::firstOrCreate(['iso2' => 'AU'], ['name' => 'Australia',
            'iso3' => 'AUD',
            'phone_code' => '61',
            'status' => true,
        ]);
        AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 1], ['name' => 'State', 'slug' => 'au_level_1', 'status' => true]);
        AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 2], ['name' => 'Local Government Area', 'slug' => 'au_level_2', 'status' => true]);
        AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 3], ['name' => 'Suburb', 'slug' => 'au_level_3', 'status' => true]);

        TenantContext::set($institute->id);

        $this->actingAs($user, 'institute_user')
            ->getJson(route('geo.levels', $au))
            ->assertOk()
            ->assertJsonPath('data.labels.1', 'State')
            ->assertJsonPath('data.labels.2', 'Local Government Area')
            ->assertJsonPath('data.labels.3', 'Suburb');
    }

    public function test_units_endpoint_returns_postal_code_for_auto_zip(): void
    {
        $institute = Institute::create([
            'name' => 'Postal Inst',
            'slug' => 'postal-inst',
            'country' => 'Australia',
            'status' => 'active',
        ]);
        $user = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Postal',
            'last_name' => 'User',
            'email' => 'postal-labels@example.test',
            'phone' => '12345679',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
        TenantContext::set($institute->id);

        $au = Country::firstOrCreate(['iso2' => 'AU'], ['name' => 'Australia',
            'iso3' => 'AUD',
            'phone_code' => '61',
            'status' => true,
        ]);
        $lvl1 = AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 1], ['name' => 'State', 'slug' => 'au_level_1', 'status' => true]);
        $lvl2 = AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 2], ['name' => 'Local Government Area', 'slug' => 'au_level_2', 'status' => true]);
        $lvl3 = AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 3], ['name' => 'Suburb', 'slug' => 'au_level_3', 'status' => true]);

        $state = \App\Models\AdministrativeUnit::create([
            'country_id' => $au->id, 'administrative_level_id' => $lvl1->id,
            'name' => 'New South Wales', 'code' => 'NSW', 'status' => true,
        ]);
        $lga = \App\Models\AdministrativeUnit::create([
            'country_id' => $au->id, 'administrative_level_id' => $lvl2->id, 'parent_id' => $state->id,
            'name' => 'Sydney City', 'code' => 'NSW.SYD', 'status' => true,
        ]);
        $suburb = \App\Models\AdministrativeUnit::create([
            'country_id' => $au->id, 'administrative_level_id' => $lvl3->id, 'parent_id' => $lga->id,
            'name' => 'Bondi', 'code' => 'NSW.SYD.BON', 'postal_code' => '2026', 'status' => true,
        ]);

        $this->actingAs($user, 'institute_user')
            ->getJson(route('geo.units', [
                'country_id' => $au->id, 'level' => 3, 'parent_id' => $lga->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.units.0.postal_code', '2026')
            ->assertJsonPath('data.units.0.name', 'Bondi');
    }
}