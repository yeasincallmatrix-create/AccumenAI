<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\Country;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Support\GeoHierarchy;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GeoAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'geo-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function country(string $iso2 = 'US', string $name = 'United States'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '1',
            'status' => true,
        ]);
    }

    private function level(Country $country, int $number, string $label, bool $status = true): AdministrativeLevel
    {
        return AdministrativeLevel::updateOrCreate(
            ['country_id' => $country->id, 'level_number' => $number],
            ['name' => $label, 'slug' => strtolower($country->iso2).'_level_'.$number, 'status' => $status]
        );
    }

    public function test_index_page_requires_platform_admin(): void
    {
        TenantContext::clear();
        $this->get(route('admin.geo.index'))->assertRedirect('/admin/login');
    }

    public function test_index_page_lists_countries(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->country('BD', 'Bangladesh');

        $this->get(route('admin.geo.index'))
            ->assertOk()
            ->assertSee('Administrative Levels')
            ->assertSee('Bangladesh');
    }

    public function test_create_country_page_renders(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->get(route('admin.geo.countries.create'))
            ->assertOk()
            ->assertSee('Add Country')
            ->assertSee('ISO 2');
    }

    public function test_platform_admin_creates_country(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->post(route('admin.geo.countries.store'), [
            'name' => 'Testland',
            'iso2' => 'ZZ',
            'iso3' => 'ZZZ',
            'phone_code' => '999',
        ])->assertRedirect(route('admin.geo.edit', Country::where('iso2', 'ZZ')->first()));

        $this->assertDatabaseHas('countries', ['iso2' => 'ZZ', 'name' => 'Testland']);
    }

    public function test_country_creation_rejects_duplicate_iso2(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $this->country('BD', 'Bangladesh');

        $this->post(route('admin.geo.countries.store'), [
            'name' => 'Bangladesh Again',
            'iso2' => 'BD',
        ])->assertSessionHasErrors('iso2');

        $this->assertSame(1, Country::where('iso2', 'BD')->count());
    }

    public function test_platform_admin_creates_three_levels_for_country(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $us = $this->country('US', 'United States');

        $this->put(route('admin.geo.update', $us), [
            'level_1' => 'State',
            'level_2' => 'County',
            'level_3' => 'City',
        ])->assertRedirect(route('admin.geo.edit', $us));

        $this->assertDatabaseHas('administrative_levels', ['country_id' => $us->id, 'level_number' => 1, 'name' => 'State', 'status' => 1]);
        $this->assertDatabaseHas('administrative_levels', ['country_id' => $us->id, 'level_number' => 2, 'name' => 'County', 'status' => 1]);
        $this->assertDatabaseHas('administrative_levels', ['country_id' => $us->id, 'level_number' => 3, 'name' => 'City', 'status' => 1]);
    }

    public function test_duplicate_level_is_upserted_not_duplicated(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');

        $this->put(route('admin.geo.update', $us), [
            'level_1' => 'Province',
            'level_2' => 'County',
            'level_3' => 'City',
        ]);

        $this->assertSame(3, AdministrativeLevel::where('country_id', $us->id)->count());
        $this->assertSame('Province', AdministrativeLevel::where('country_id', $us->id)->where('level_number', 1)->value('name'));
    }

    public function test_blank_level_disables_it(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State', true);

        $this->put(route('admin.geo.update', $us), [
            'level_1' => '',
            'level_2' => 'County',
            'level_3' => '',
        ]);

        $this->assertFalse((bool) AdministrativeLevel::where('country_id', $us->id)->where('level_number', 1)->value('status'));
        $this->assertFalse((bool) AdministrativeLevel::where('country_id', $us->id)->where('level_number', 3)->value('status'));
        $this->assertTrue((bool) AdministrativeLevel::where('country_id', $us->id)->where('level_number', 2)->value('status'));
    }

    public function test_country_can_be_disabled(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $bd = $this->country('BD', 'Bangladesh');

        $this->post(route('admin.geo.toggle', $bd))->assertRedirect(route('admin.geo.edit', $bd));

        $this->assertFalse((bool) Country::find($bd->id)->status);

        $this->post(route('admin.geo.toggle', $bd));
        $this->assertTrue((bool) Country::find($bd->id)->status);
    }

    public function test_institute_user_cannot_access_admin_geo(): void
    {
        TenantContext::clear();

        $institute = \App\Models\Institute::create([
            'name' => 'Geo Deny '.mt_rand(1000, 9999),
            'slug' => 'geo-deny-'.mt_rand(1000, 9999),
            'status' => 'active',
        ]);
        $role = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail();
        $user = InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => 'geo-user@example.test',
            'phone' => '0170'.substr(md5('geo-user@example.test'), 0, 7),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($user, 'institute_user')
            ->get(route('admin.geo.index'))
            ->assertRedirect('/admin/login');

        $this->actingAs($user, 'institute_user')
            ->post(route('admin.geo.countries.store'), ['name' => 'X', 'iso2' => 'XX'])
            ->assertRedirect('/admin/login');
    }

    public function test_bangladesh_configuration_resolves_dynamic_labels(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $bd = $this->country('BD', 'Bangladesh');
        $this->level($bd, 1, 'Division');
        $this->level($bd, 2, 'District');
        $this->level($bd, 3, 'Upazila');

        $labels = GeoHierarchy::levelLabels($bd);

        $this->assertSame('Division', $labels[1]);
        $this->assertSame('District', $labels[2]);
        $this->assertSame('Upazila', $labels[3]);
    }

    public function test_united_states_configuration_resolves_dynamic_labels(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');
        $this->level($us, 3, 'City');

        $labels = GeoHierarchy::levelLabels($us);

        $this->assertSame('State', $labels[1]);
        $this->assertSame('County', $labels[2]);
        $this->assertSame('City', $labels[3]);
    }

    public function test_different_countries_use_different_terminology(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $bd = $this->country('BD', 'Bangladesh');
        $this->level($bd, 1, 'Division');
        $this->level($bd, 2, 'District');
        $this->level($bd, 3, 'Upazila');

        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');
        $this->level($us, 3, 'City');

        $this->assertNotSame(GeoHierarchy::levelLabels($bd), GeoHierarchy::levelLabels($us));
        $this->assertSame('Division', GeoHierarchy::levelLabels($bd)[1]);
        $this->assertSame('State', GeoHierarchy::levelLabels($us)[1]);
    }

    public function test_levels_endpoint_returns_configured_labels(): void
    {
        TenantContext::clear();
        $this->actingAs($this->platformAdmin(), 'platform_admin');

        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');
        $this->level($us, 3, 'City');

        $this->getJson(route('geo.levels', $us))
            ->assertOk()
            ->assertJsonPath('data.labels.1', 'State')
            ->assertJsonPath('data.labels.2', 'County')
            ->assertJsonPath('data.labels.3', 'City');
    }
}
