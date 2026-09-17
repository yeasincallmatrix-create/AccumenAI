<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Medicine;
use App\Models\Medical\NumberSequence;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\MedicineCodeService;
use App\Services\Medical\NumberSequenceService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicineCodeBarcodeTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Code Test Hospital',
            'slug' => 'code-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function makeMedicine(array $overrides = []): Medicine
    {
        return Medicine::create(array_merge([
            'institute_id' => $this->institute->id,
            'generic_name' => 'Codemycin',
            'brand_name' => 'Codemycin ' . uniqid(),
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ], $overrides));
    }

    private function service(): MedicineCodeService
    {
        return app(MedicineCodeService::class);
    }

    // ---------- Sequential code generation ----------

    public function test_first_medicine_gets_1000(): void
    {
        $medicine = $this->makeMedicine(['code' => null]);

        $this->assertEquals('1000', $medicine->code);
    }

    public function test_second_medicine_gets_1001(): void
    {
        $this->makeMedicine(['code' => null]);
        $second = $this->makeMedicine(['code' => null]);

        $this->assertEquals('1001', $second->code);
    }

    public function test_sequential_increment_skips_gaps(): void
    {
        $this->makeMedicine(['code' => '1000']);
        $this->makeMedicine(['code' => '1002']);

        // First gap (1001) is recycled…
        $this->assertEquals('1001', $this->service()->reserveCode($this->institute->id));
        // …while suggestion follows the highest + 1.
        $this->assertEquals('1003', $this->service()->suggestNext($this->institute->id));
    }

    public function test_after_9999_next_is_10000(): void
    {
        $this->makeMedicine(['code' => '9999']);

        $this->assertEquals('10000', $this->service()->suggestNext($this->institute->id));
        $this->assertEquals('1000', $this->service()->reserveCode($this->institute->id));
    }

    public function test_after_99999_next_is_100000(): void
    {
        $this->makeMedicine(['code' => '99999']);

        $this->assertEquals('100000', $this->service()->suggestNext($this->institute->id));
    }

    public function test_after_999999_returns_null(): void
    {
        $this->makeMedicine(['code' => '999999']);

        // Increment path is exhausted…
        $this->assertNull($this->service()->suggestNext($this->institute->id));
        // …but gap-fill still recycles free lower numbers.
        $this->assertEquals('1000', $this->service()->reserveCode($this->institute->id));
    }

    public function test_exhausted_institute_throws_on_create(): void
    {
        // Simulate a fully exhausted institute by stubbing the service.
        $stub = new class extends MedicineCodeService
        {
            public function reserveCode(int $instituteId): ?string
            {
                return null;
            }
        };
        app()->instance(MedicineCodeService::class, $stub);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/capacity exhausted/');
        $this->makeMedicine(['code' => null]);
    }

    // ---------- Per-tenant uniqueness ----------

    public function test_same_code_allowed_in_different_institutes(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'code-other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->makeMedicine(['code' => '1000']);
        $this->makeMedicine(['institute_id' => $other->id, 'code' => '1000']);

        $this->assertEquals(2, Medicine::where('code', '1000')->count());
    }

    public function test_same_code_blocked_in_same_institute(): void
    {
        $this->makeMedicine(['code' => '4585']);

        $response = $this->post(route('medical.pharmacy.medicines.store'), [
            'code' => '4585',
            'generic_name' => 'Dup',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 1,
            'selling_price' => 2,
            'reorder_level' => 1,
            'reorder_quantity' => 5,
        ]);
        $response->assertSessionHasErrors('code');
    }

    public function test_each_institute_has_separate_sequence(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital 2',
            'slug' => 'code-other2-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->makeMedicine(['code' => null]);
        $otherFirst = $this->makeMedicine(['institute_id' => $other->id, 'code' => null]);

        $this->assertEquals('1000', $otherFirst->code);
    }

    // ---------- Suggest & peek ----------

    public function test_suggest_next_when_empty(): void
    {
        $this->assertEquals('1000', $this->service()->suggestNext($this->institute->id));
        $this->assertEquals('1000', $this->service()->peekNextCode($this->institute->id));
    }

    public function test_suggest_next_increments_from_highest(): void
    {
        $this->makeMedicine(['code' => '4585']);

        $this->assertEquals('4586', $this->service()->suggestNext($this->institute->id));
    }

    public function test_suggest_next_crosses_slab_boundary(): void
    {
        $this->makeMedicine(['code' => '9999']);

        $this->assertEquals('10000', $this->service()->suggestNext($this->institute->id));
    }

    public function test_peek_finds_first_gap(): void
    {
        $this->makeMedicine(['code' => '1001']);

        $this->assertEquals('1000', $this->service()->peekNextCode($this->institute->id));
    }

    // ---------- Slab info ----------

    public function test_slab_info_counts_correctly(): void
    {
        $this->makeMedicine(['code' => '1000']);
        $this->makeMedicine(['code' => '1001']);
        $this->makeMedicine(['code' => '10000']);
        $this->makeMedicine(['code' => 'MED-LEGACY-1']);

        $info = $this->service()->slabInfo($this->institute->id);

        $this->assertEquals(2, $info[0]['used']);
        $this->assertEquals(9000, $info[0]['capacity']);
        $this->assertEquals(1, $info[1]['used']);
        $this->assertEquals(0, $info[2]['used']);
    }

    public function test_slab_info_percentages(): void
    {
        $info = $this->service()->slabInfo($this->institute->id);

        $this->assertEquals(0.0, $info[0]['percent_used']);
        $this->assertEquals(9000, $info[0]['available']);
    }

    // ---------- Custom code ----------

    public function test_custom_code_4_digits_allowed(): void
    {
        $response = $this->post(route('medical.pharmacy.medicines.store'), $this->storePayload(['code' => '4585']));
        $response->assertRedirect();
        $this->assertDatabaseHas('medicines', ['code' => '4585', 'institute_id' => $this->institute->id]);
    }

    public function test_custom_code_6_digits_allowed(): void
    {
        $response = $this->post(route('medical.pharmacy.medicines.store'), $this->storePayload(['code' => '123456']));
        $response->assertRedirect();
    }

    public function test_custom_code_7_digits_rejected(): void
    {
        $response = $this->post(route('medical.pharmacy.medicines.store'), $this->storePayload(['code' => '1234567']));
        $response->assertSessionHasErrors('code');
    }

    public function test_custom_code_letters_rejected(): void
    {
        $response = $this->post(route('medical.pharmacy.medicines.store'), $this->storePayload(['code' => 'ABCD']));
        $response->assertSessionHasErrors('code');
    }

    public function test_custom_code_duplicate_rejected(): void
    {
        $this->makeMedicine(['code' => '7777']);

        $response = $this->post(route('medical.pharmacy.medicines.store'), $this->storePayload(['code' => '7777']));
        $response->assertSessionHasErrors('code');
    }

    // ---------- Concurrency ----------

    public function test_concurrent_creation_does_not_duplicate_codes(): void
    {
        // Simulate interleaved reservations: each reserve-then-create pair
        // must land on a distinct code even when gaps exist.
        $service = $this->service();
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $code = $service->reserveCode($this->institute->id);
            $this->makeMedicine(['code' => $code, 'brand_name' => 'Race ' . $i . ' ' . uniqid()]);
            $codes[] = $code;
        }

        $this->assertCount(5, array_unique($codes));
        $this->assertEquals(['1000', '1001', '1002', '1003', '1004'], $codes);
    }

    // ---------- Preservation ----------

    public function test_existing_legacy_MED_codes_unchanged(): void
    {
        $legacy = $this->makeMedicine(['code' => 'MED-189-ABC123']);

        $legacy->update(['selling_price' => 99]);

        $this->assertEquals('MED-189-ABC123', $legacy->refresh()->code);
        // Legacy codes are invisible to the numeric sequence.
        $this->assertEquals('1000', $this->service()->suggestNext($this->institute->id));
    }

    public function test_existing_numeric_codes_unchanged(): void
    {
        $existing = $this->makeMedicine(['code' => '4321']);

        $existing->update(['selling_price' => 42]);

        $this->assertEquals('4321', $existing->refresh()->code);
    }

    // ---------- UI ----------

    public function test_create_form_shows_suggestion(): void
    {
        $response = $this->get(route('medical.pharmacy.medicines.create'));
        $response->assertStatus(200);
        $response->assertSee('Next suggested code');
        $response->assertSee('1000');
    }

    public function test_show_page_renders_barcode_container(): void
    {
        $medicine = $this->makeMedicine(['code' => '5555']);

        $response = $this->get(route('medical.pharmacy.medicines.show', $medicine));
        $response->assertStatus(200);
        $response->assertSee('barcode-svg', false);
        $response->assertSee('Print Barcode');
    }

    public function test_index_shows_capacity_and_bulk_controls(): void
    {
        $response = $this->get(route('medical.pharmacy.medicines.index'));
        $response->assertStatus(200);
        $response->assertSee('Code Capacity');
        $response->assertSee('Print Selected Barcodes');
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'generic_name' => 'Payloadmycin',
            'brand_name' => 'Payload ' . uniqid(),
            'dosage_form' => 'Tablet',
            'strength' => '250mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
        ], $overrides);
    }
}
