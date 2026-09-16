<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Medical\PharmacyStock;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicineScopesTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Scope Test Hospital',
            'slug' => 'scope-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'web');
        \App\Support\Workspace::set($this->institute->id);
    }

    private function makeMedicine(array $overrides = []): Medicine
    {
        $unique = strtoupper(uniqid());
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'SCP-'.$unique,
            'generic_name' => 'Scopemycin-'.$unique,
            'brand_name' => 'Scopemycin-'.$unique,
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
            'is_controlled' => false,
            'requires_prescription' => false,
        ], $overrides));
    }

    private function makeStock(Medicine $medicine, int $qty): void
    {
        PharmacyStock::create([
            'institute_id' => $this->institute->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-SCP-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear(),
            'quantity_received' => $qty,
            'current_quantity' => $qty,
            'purchase_price' => 5,
            'selling_price' => 8,
            'received_date' => now(),
        ]);
    }

    // ─── Active / Inactive ────────────────────────────────────

    public function test_active_scope_returns_only_active(): void
    {
        $this->makeMedicine(['is_active' => true]);
        $this->makeMedicine(['is_active' => true]);
        $this->makeMedicine(['is_active' => false]);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->active()->count());
    }

    public function test_inactive_scope_returns_only_inactive(): void
    {
        $this->makeMedicine(['is_active' => true]);
        $this->makeMedicine(['is_active' => false]);
        $this->makeMedicine(['is_active' => false]);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->inactive()->count());
    }

    // ─── Controlled ───────────────────────────────────────────

    public function test_controlled_scope_filters_controlled(): void
    {
        $this->makeMedicine(['is_controlled' => true]);
        $this->makeMedicine(['is_controlled' => false]);
        $this->makeMedicine(['is_controlled' => false]);

        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->controlled()->count());
    }

    // ─── Rx / OTC ─────────────────────────────────────────────

    public function test_requires_prescription_scope_filters_rx_only(): void
    {
        $this->makeMedicine(['requires_prescription' => true]);
        $this->makeMedicine(['requires_prescription' => true]);
        $this->makeMedicine(['requires_prescription' => false]);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->requiresPrescription()->count());
    }

    public function test_over_the_counter_scope_filters_otc(): void
    {
        $this->makeMedicine(['requires_prescription' => true]);
        $this->makeMedicine(['requires_prescription' => false]);
        $this->makeMedicine(['requires_prescription' => false]);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->overTheCounter()->count());
    }

    // ─── DGDA ─────────────────────────────────────────────────

    public function test_dgda_coded_scope_filters_synced(): void
    {
        $this->makeMedicine(['dgda_code' => 'DG-001']);
        $this->makeMedicine(['dgda_code' => null]);
        $this->makeMedicine(['dgda_code' => '']);

        $this->assertEquals(1, Medicine::where('institute_id', $this->institute->id)->dgdaCoded()->count());
    }

    public function test_dgda_pending_scope_filters_unsynced(): void
    {
        $this->makeMedicine(['dgda_code' => 'DG-001']);
        $this->makeMedicine(['dgda_code' => null]);
        $this->makeMedicine(['dgda_code' => '']);

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->dgdaPending()->count());
    }

    // ─── Dosage Form ──────────────────────────────────────────

    public function test_of_form_scope_filters_by_dosage_form(): void
    {
        $this->makeMedicine(['dosage_form' => 'Tablet']);
        $this->makeMedicine(['dosage_form' => 'Tablet']);
        $this->makeMedicine(['dosage_form' => 'Capsule']);

        $q = Medicine::where('institute_id', $this->institute->id);
        $this->assertEquals(2, (clone $q)->ofForm('Tablet')->count());
        $this->assertEquals(1, (clone $q)->ofForm('Capsule')->count());
    }

    // ─── Stock Scopes ─────────────────────────────────────────

    public function test_low_stock_scope_returns_below_reorder(): void
    {
        $low = $this->makeMedicine(['reorder_level' => 20]);
        $this->makeStock($low, 5);

        $ok = $this->makeMedicine(['reorder_level' => 10]);
        $this->makeStock($ok, 50);

        $noReorder = $this->makeMedicine(['reorder_level' => 0]);
        $this->makeStock($noReorder, 2);

        $q = Medicine::where('institute_id', $this->institute->id);
        $this->assertEquals(1, (clone $q)->lowStock()->count());
        $this->assertEquals($low->id, (clone $q)->lowStock()->first()->id);
    }

    public function test_out_of_stock_scope_returns_zero_quantity(): void
    {
        $out = $this->makeMedicine();
        $this->makeStock($out, 0);

        $has = $this->makeMedicine();
        $this->makeStock($has, 10);

        $q = Medicine::where('institute_id', $this->institute->id);
        $this->assertEquals(1, (clone $q)->outOfStock()->count());
        $this->assertEquals($out->id, (clone $q)->outOfStock()->first()->id);
    }

    // ─── Soft Deletes ─────────────────────────────────────────

    public function test_scopes_exclude_soft_deleted_by_default(): void
    {
        $a = $this->makeMedicine(['is_active' => true]);
        $b = $this->makeMedicine(['is_active' => true]);
        $c = $this->makeMedicine(['is_active' => true]);

        $c->delete();

        $this->assertEquals(2, Medicine::where('institute_id', $this->institute->id)->active()->count());
    }

    // ─── Combined Scopes ──────────────────────────────────────

    public function test_scopes_can_be_chained(): void
    {
        $this->makeMedicine(['is_active' => true, 'requires_prescription' => true, 'dosage_form' => 'Tablet']);
        $this->makeMedicine(['is_active' => true, 'requires_prescription' => false, 'dosage_form' => 'Tablet']);
        $this->makeMedicine(['is_active' => false, 'requires_prescription' => true, 'dosage_form' => 'Tablet']);

        $count = Medicine::where('institute_id', $this->institute->id)
            ->active()->requiresPrescription()->ofForm('Tablet')->count();
        $this->assertEquals(1, $count);
    }

    // ─── ForIndex ─────────────────────────────────────────────

    public function test_for_index_scope_returns_active_ordered_by_brand(): void
    {
        $this->makeMedicine(['brand_name' => 'Zebra Med', 'strength' => null]);
        $this->makeMedicine(['brand_name' => 'Alpha Med', 'strength' => null]);
        $this->makeMedicine(['brand_name' => 'Inactive Med', 'is_active' => false, 'strength' => null]);

        $results = Medicine::where('institute_id', $this->institute->id)
            ->forIndex()->pluck('brand_name')->toArray();
        $this->assertCount(2, $results);
        $this->assertEquals('Alpha Med', $results[0]);
        $this->assertEquals('Zebra Med', $results[1]);
    }
}
