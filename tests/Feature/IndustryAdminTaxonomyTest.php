<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Services\IndustryService;
use App\Support\IndustryRules;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IndustryAdminTaxonomyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\IndustryTaxonomySeeder']);
        Cache::flush();
    }

    protected function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'taxonomy-admin@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function actingAsAdmin(): void
    {
        $this->actingAs($this->admin(), 'platform_admin');
    }

    // ── DB-backed IndustryRules / service behavior ──────────────────────────

    public function test_db_backed_industries_and_labels(): void
    {
        $industries = IndustryRules::industries(null);

        $this->assertArrayHasKey('education', $industries);
        $this->assertSame('Education', $industries['education']);
        $this->assertSame('Education', IndustryRules::label('Bangladesh', 'education'));
        $this->assertSame('Madrasha', IndustryRules::label('Bangladesh', 'education', 'madrasha'));
        $this->assertNull(IndustryRules::label('Bangladesh', 'unknown'));
    }

    public function test_db_backed_sub_industries_country_scoped(): void
    {
        $subs = IndustryRules::subIndustries('Bangladesh', 'education');

        $this->assertArrayHasKey('school', $subs);
        $this->assertArrayHasKey('madrasha', $subs);
        $this->assertTrue(IndustryRules::hasSubIndustries('Bangladesh', 'education'));
        $this->assertFalse(IndustryRules::hasSubIndustries('Bangladesh', 'real_estate'));
        // A country name with no Country row offers no sub-industries
        // (config contract parity: unlisted countries get none).
        $this->assertSame([], IndustryRules::subIndustries('Neverlandia', 'education'));
        $this->assertSame([], IndustryService::subIndustriesBySlug('Neverlandia', 'education'));
        // France exists as a country row but has no scoped taxonomy rows,
        // so the global sub-industries apply to it by design.
        $this->assertNotEmpty(IndustryRules::subIndustries('France', 'education'));
    }

    public function test_industries_are_global_country_id_is_ignored_by_design(): void
    {
        $bdId = Country::where('name', 'Bangladesh')->value('id');
        $this->assertNotNull($bdId);

        $this->assertSame(IndustryService::industries(null), IndustryService::industries($bdId));
        $this->assertSame(IndustryRules::industries(null), IndustryRules::industries('Bangladesh'));
    }

    public function test_inactive_industry_and_sub_are_excluded(): void
    {
        $industry = Industry::where('slug', 'retail')->firstOrFail();
        $industry->update(['status' => 'inactive']);

        $this->assertArrayNotHasKey('retail', IndustryRules::industries(null));

        $edu = Industry::where('slug', 'education')->firstOrFail();
        $sub = $edu->subIndustries()->where('slug', 'school')->whereNull('country_id')->firstOrFail();
        $sub->update(['status' => 'inactive']);

        $this->assertArrayNotHasKey('school', IndustryRules::subIndustries('', 'education'));
    }

    // ── Authorization ───────────────────────────────────────────────────────

    public function test_guest_cannot_access_industry_admin(): void
    {
        $this->get(route('admin.industries.index'))->assertRedirect();
        $this->get(route('admin.industries.create'))->assertRedirect();
    }

    public function test_admin_can_view_index_and_create(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.industries.index'))->assertOk();
        $this->get(route('admin.industries.create'))->assertOk();
    }

    // ── Industry CRUD ───────────────────────────────────────────────────────

    public function test_create_industry(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.industries.store'), [
            'name' => 'Test Industry',
            'slug' => 'test_industry',
        ]);

        $response->assertRedirect(route('admin.industries.index'));
        $this->assertDatabaseHas('industries', ['slug' => 'test_industry', 'name' => 'Test Industry']);
    }

    public function test_duplicate_slug_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.industries.store'), ['name' => 'Education Dupe', 'slug' => 'education'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Industry::where('slug', 'education')->count());
    }

    public function test_update_industry(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'retail')->firstOrFail();

        $this->put(route('admin.industries.update', $industry), [
            'name' => 'Retail Renamed',
            'slug' => 'retail',
        ])->assertRedirect(route('admin.industries.index'));

        $this->assertSame('Retail Renamed', $industry->fresh()->name);
    }

    public function test_delete_industry_without_dependencies(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::create(['name' => 'Disposable', 'slug' => 'disposable']);

        $this->delete(route('admin.industries.destroy', $industry))
            ->assertRedirect(route('admin.industries.index'));

        $this->assertDatabaseMissing('industries', ['slug' => 'disposable']);
    }

    public function test_delete_blocked_when_institutes_exist(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        Institute::create([
            'name' => 'Dep-' . uniqid(),
            'slug' => 'dep-' . uniqid(),
            'country' => 'Bangladesh',
            'status' => 'active',
            'industry' => 'education',
            'sub_industry' => 'school',
            'industry_id' => $industry->id,
        ]);

        $this->delete(route('admin.industries.destroy', $industry))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('industries', ['id' => $industry->id]);
    }

    public function test_delete_blocked_when_sub_industries_exist(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $this->assertTrue($industry->subIndustries()->exists());

        $this->delete(route('admin.industries.destroy', $industry))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('industries', ['id' => $industry->id]);
    }

    // ── Mass assignment hardening ───────────────────────────────────────────

    public function test_industry_generated_fields_not_mass_assignable(): void
    {
        $industry = Industry::create([
            'id' => 987654321,
            'name' => 'Mass',
            'slug' => 'mass_' . uniqid(),
        ]);

        $this->assertNotSame(987654321, $industry->id);
        $this->assertDatabaseHas('industries', ['slug' => $industry->slug]);
    }

    public function test_sub_industry_scope_hash_and_id_not_mass_assignable(): void
    {
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $sub = \App\Models\SubIndustry::create([
            'id' => 987654320,
            'industry_id' => $industry->id,
            'country_id' => null,
            'name' => 'Mass Sub',
            'slug' => 'mass_sub_' . uniqid(),
            'status' => 'active',
            'scope_hash' => 'HACKED',
        ]);

        $fresh = $sub->fresh();
        $this->assertNotSame(987654320, $fresh->id);
        $this->assertNotSame('HACKED', $fresh->scope_hash);
        $this->assertStringContainsString($fresh->slug, $fresh->scope_hash);
    }
}
