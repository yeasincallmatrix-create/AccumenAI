<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 — 10-layer module resolution verification against real
 * Phase 3 seeded data (industry_subcategories, subcategory_default_modules,
 * country_tax_modules) on monetix_test.
 *
 * Hard boundaries under test:
 *   Layer 7 — industry boundary (medical.* only for healthcare, ...)
 *   Layer 8 — country tax filter (BD → vat/tds only; cannot be bypassed
 *             even by a direct tenant override row)
 */
class ModuleResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModuleAccessService::class);
    }

    public function test_pharmacy_tenant_bd_gets_correct_modules(): void
    {
        $inst = $this->makeSavedInstitute('healthcare', 'pharmacy', 'BD', $this->packageId('basic'));

        $enabled = $this->enabledKeys($inst);

        // Layer 3 — sub-category mandatory/default (Phase 3 seeded rows)
        $this->assertContains('medical.pharmacy', $enabled);
        $this->assertContains('medical.billing', $enabled);
        $this->assertContains('medical.records', $enabled);

        // Layer 1 core + Layer 4 package (package 2 includes tds)
        $this->assertContains('vat', $enabled);
        $this->assertContains('tds', $enabled);

        // Layer 8 hard country boundary — BD allows vat/tds only
        $this->assertNotContains('gst', $enabled);
        $this->assertNotContains('sales_tax', $enabled);

        // Layer 7 hard industry boundary — no education/training leakage
        $this->assertEmpty(
            array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'education') || str_starts_with($m, 'training_center'))),
            'Healthcare tenant must not receive education/training modules'
        );
    }

    public function test_education_tenant_cannot_get_medical_modules(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $enabled = $this->enabledKeys($inst);

        $medicalModules = array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'medical')));
        $this->assertEmpty($medicalModules, 'Education tenant should not have medical modules');

        // Layer 3 still applies for the education sub-category
        $this->assertContains('education.fees', $enabled);
        $this->assertContains('education.students', $enabled);
    }

    public function test_bd_tenant_cannot_get_gst_even_via_override(): void
    {
        $inst = $this->makeSavedInstitute('retail', 'grocery', 'BD', $this->packageId('basic'));
        $enabled = $this->enabledKeys($inst);

        $this->assertContains('vat', $enabled);
        $this->assertNotContains('gst', $enabled);
        $this->assertNotContains('sales_tax', $enabled);

        // Hard boundary: even a direct tenant override cannot enable a
        // country-disallowed tax module (Layer 8 runs AFTER Layer 5).
        DB::table('institute_module_overrides')->insert([
            ['institute_id' => $inst->id, 'module_key' => 'gst', 'enabled' => true, 'reason' => 'phase4 boundary probe', 'created_at' => now(), 'updated_at' => now()],
            ['institute_id' => $inst->id, 'module_key' => 'sales_tax', 'enabled' => true, 'reason' => 'phase4 boundary probe', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->service->flushCache($inst->id);

        $forced = $this->enabledKeys($inst);
        $this->assertNotContains('gst', $forced, 'Layer 8 must veto gst despite tenant override');
        $this->assertNotContains('sales_tax', $forced, 'Layer 8 must veto sales_tax despite tenant override');
    }

    public function test_training_center_gets_training_modules(): void
    {
        $inst = $this->makeInstitute('training_center', 'it_training', 'BD');
        $enabled = $this->enabledKeys($inst);

        $trainingModules = array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'training_center')));
        $this->assertNotEmpty($trainingModules, 'Training tenant must resolve training_center modules');

        // Phase 3 sub-category mapping keys (registry-validated in Phase 3)
        $this->assertContains('training_center.courses', $enabled);
        $this->assertContains('training_center.batches', $enabled);
        $this->assertContains('training_center.students', $enabled);
        $this->assertContains('training_center.certificates', $enabled);
        $this->assertContains('training_center.classes', $enabled);

        // Layer 7 — no medical leakage
        $this->assertEmpty(
            array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'medical'))),
            'Training tenant must not receive medical modules'
        );
    }

    public function test_cache_flushes_correctly(): void
    {
        $inst = Institute::first();
        if (! $inst) {
            $this->markTestSkipped('No institute');
        }

        $before = $this->service->getEnabledModules($inst);
        $this->service->flushCache($inst->id);
        $after = $this->service->getEnabledModules($inst);

        $this->assertEquals(count($before), count($after));
        $this->assertEquals($before, $after, 'Re-resolution after flush must be identical');
    }

    /**
     * resolveEnabled() returns module_key => bool — extract enabled keys.
     */
    protected function enabledKeys(Institute $inst): array
    {
        return array_keys(array_filter($this->service->resolveEnabled($inst)));
    }

    /**
     * Unsaved institute — resolution reads attributes only (no override /
     * entitlement / subscription rows exist for a null id).
     */
    protected function makeInstitute(string $industry, string $subcategory, string $country): Institute
    {
        $inst = new Institute();
        $inst->industry = $industry;
        $inst->subcategory_key = $subcategory;
        $inst->country_code = $country;
        $inst->status = 'active';

        return $inst;
    }

    /**
     * Package ids differ per database (accumen_ai: 1-4, monetix_test:
     * 900-903) — resolve by slug so the test runs on both.
     */
    protected function packageId(string $slug): int
    {
        $id = DB::table('subscription_packages')->where('slug', $slug)->value('id');
        $this->assertNotNull($id, "Package {$slug} missing");

        return (int) $id;
    }

    /**
     * Persisted institute (+ active subscription so Layer 4 resolves the
     * given package instead of falling back to free). withoutEvents()
     * bypasses the B17 auto-assign-PREMIUM creating hook so package_id
     * stays as given. All rows roll back with DatabaseTransactions.
     */
    protected function makeSavedInstitute(string $industry, string $subcategory, string $country, int $packageId): Institute
    {
        $suffix = substr(uniqid(), -8);

        $inst = Institute::withoutEvents(function () use ($industry, $subcategory, $country, $packageId, $suffix) {
            $inst = Institute::create([
                'name' => "Phase4 {$industry} {$suffix}",
                'slug' => "phase4-{$industry}-{$suffix}",
                'status' => 'active',
                'country' => 'Bangladesh',
                'industry' => $industry,
                'package_id' => $packageId,
            ]);
            // Not in $fillable — assign directly so mass assignment
            // cannot silently drop the Layer 3 / Layer 8 attributes.
            $inst->subcategory_key = $subcategory;
            $inst->country_code = $country;
            $inst->save();

            return $inst;
        });

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $inst->id,
            'package_id' => $packageId,
            'billing_cycle' => 'yearly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $inst->fresh();
    }
}
