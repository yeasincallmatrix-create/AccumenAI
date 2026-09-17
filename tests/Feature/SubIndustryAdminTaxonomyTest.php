<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubIndustry;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubIndustryAdminTaxonomyTest extends TestCase
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

    protected function actingAsAdmin(): void
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'sub-taxonomy-admin@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_admin');
    }

    protected function bangladeshId(): int
    {
        $id = Country::where('name', 'Bangladesh')->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }

    // ── Authorization ───────────────────────────────────────────────────────

    public function test_guest_cannot_access_sub_industry_admin(): void
    {
        $industry = Industry::where('slug', 'education')->firstOrFail();

        $this->get(route('admin.industries.sub-industry.create', $industry))->assertRedirect();
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function test_create_global_sub_industry(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'retail')->firstOrFail();

        $this->post(route('admin.industries.sub-industry.store', $industry), [
            'name' => 'Corner Shop',
            'slug' => 'corner_shop',
        ])->assertRedirect(route('admin.industries.sub-industries', $industry));

        $this->assertDatabaseHas('sub_industries', [
            'industry_id' => $industry->id,
            'country_id' => null,
            'slug' => 'corner_shop',
        ]);
    }

    public function test_create_country_specific_sub_industry(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'retail')->firstOrFail();

        $this->post(route('admin.industries.sub-industry.store', $industry), [
            'name' => 'Corner Shop',
            'slug' => 'corner_shop',
            'countries' => [$this->bangladeshId()],
        ])->assertRedirect(route('admin.industries.sub-industries', $industry));

        $this->assertDatabaseHas('sub_industries', [
            'industry_id' => $industry->id,
            'country_id' => $this->bangladeshId(),
            'slug' => 'corner_shop',
        ]);
    }

    public function test_duplicate_scoped_slug_does_not_duplicate(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'retail')->firstOrFail();
        $payload = ['name' => 'Corner Shop', 'slug' => 'corner_shop'];

        $this->post(route('admin.industries.sub-industry.store', $industry), $payload)->assertRedirect();
        $this->post(route('admin.industries.sub-industry.store', $industry), $payload)->assertRedirect();

        // The store upserts within (industry, country, slug) scope — no duplicates.
        $this->assertSame(1, SubIndustry::where('industry_id', $industry->id)
            ->whereNull('country_id')
            ->where('slug', 'corner_shop')
            ->count());
    }

    // ── Update / validation ─────────────────────────────────────────────────

    public function test_update_sub_industry(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $sub = $industry->subIndustries()->where('slug', 'school')->whereNull('country_id')->firstOrFail();

        $this->put(route('admin.industries.sub-industry.update', [$industry, $sub]), [
            'name' => 'School Renamed',
            'slug' => 'school',
        ])->assertRedirect(route('admin.industries.sub-industries', $industry));

        $this->assertSame('School Renamed', $sub->fresh()->name);
    }

    public function test_country_validation_rejects_unknown_country(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $sub = $industry->subIndustries()->where('slug', 'school')->whereNull('country_id')->firstOrFail();

        $this->put(route('admin.industries.sub-industry.update', [$industry, $sub]), [
            'name' => 'School',
            'slug' => 'school',
            'country_id' => 999999,
        ])->assertSessionHasErrors('country_id');
    }

    // ── Delete safety ───────────────────────────────────────────────────────

    public function test_delete_without_dependencies(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'retail')->firstOrFail();
        $sub = SubIndustry::create([
            'industry_id' => $industry->id,
            'country_id' => null,
            'name' => 'Disposable Sub',
            'slug' => 'disposable_sub',
            'status' => 'active',
        ]);

        $this->delete(route('admin.industries.sub-industry.destroy', [$industry, $sub]))
            ->assertRedirect(route('admin.industries.sub-industries', $industry));

        $this->assertDatabaseMissing('sub_industries', ['id' => $sub->id]);
    }

    public function test_delete_blocked_when_institutes_depend_on_it(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $sub = $industry->subIndustries()->where('slug', 'school')->whereNull('country_id')->firstOrFail();
        Institute::create([
            'name' => 'Dep-' . uniqid(),
            'slug' => 'dep-' . uniqid(),
            'country' => 'Bangladesh',
            'country_id' => $this->bangladeshId(),
            'status' => 'active',
            'industry' => 'education',
            'sub_industry' => 'school',
            'industry_id' => $industry->id,
            'sub_industry_id' => $sub->id,
        ]);

        $this->delete(route('admin.industries.sub-industry.destroy', [$industry, $sub]))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('sub_industries', ['id' => $sub->id]);
    }

    // ── Parent industry mismatch ────────────────────────────────────────────

    public function test_parent_mismatch_is_404_for_all_nested_operations(): void
    {
        $this->actingAsAdmin();
        $industryA = Industry::where('slug', 'education')->firstOrFail();
        $industryB = Industry::where('slug', 'healthcare')->firstOrFail();
        $subA = $industryA->subIndustries()->where('slug', 'school')->whereNull('country_id')->firstOrFail();

        $this->get(route('admin.industries.sub-industry.edit', [$industryB, $subA]))->assertNotFound();

        $this->put(route('admin.industries.sub-industry.update', [$industryB, $subA]), [
            'name' => 'Hijacked',
            'slug' => 'school',
        ])->assertNotFound();

        $this->post(route('admin.industries.sub-industry.toggle', [$industryB, $subA]))->assertNotFound();

        $this->delete(route('admin.industries.sub-industry.destroy', [$industryB, $subA]))->assertNotFound();

        // Nothing was touched.
        $this->assertSame('School', $subA->fresh()->name);
        $this->assertSame('active', $subA->fresh()->status);
        $this->assertDatabaseHas('sub_industries', ['id' => $subA->id]);
    }

    public function test_toggle_sub_industry(): void
    {
        $this->actingAsAdmin();
        $industry = Industry::where('slug', 'education')->firstOrFail();
        $sub = $industry->subIndustries()->where('slug', 'college')->whereNull('country_id')->firstOrFail();

        $this->post(route('admin.industries.sub-industry.toggle', [$industry, $sub]))
            ->assertRedirect(route('admin.industries.sub-industries', $industry));

        $this->assertSame('inactive', $sub->fresh()->status);
    }
}
