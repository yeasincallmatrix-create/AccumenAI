<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Institute;
use App\Models\Party;

class PartyTypeTest extends TestCase
{
    protected Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institute = Institute::create([
            'name' => 'Test Institute ' . uniqid(),
            'slug' => 'test-institute-' . uniqid(),
            'status' => 'active',
            'industry' => 'retail',
            'country' => 'Bangladesh',
        ]);
    }

    /** @test */
    public function party_can_be_customer_only()
    {
        $party = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'is_customer' => true,
            'is_vendor' => false,
        ]);
        $this->assertEquals('customer', $party->fresh()->party_type);
        $this->assertEquals('Customer', $party->display_type);
    }

    /** @test */
    public function party_can_be_vendor_only()
    {
        $party = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'is_customer' => false,
            'is_vendor' => true,
        ]);
        $this->assertEquals('vendor', $party->fresh()->party_type);
    }

    /** @test */
    public function party_can_be_both()
    {
        $party = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'is_customer' => true,
            'is_vendor' => true,
        ]);
        $this->assertEquals('both', $party->fresh()->party_type);
        $this->assertEquals('Customer & Vendor', $party->display_type);
    }

    /** @test */
    public function party_type_auto_syncs_on_update()
    {
        $party = Party::factory()->create([
            'institute_id' => $this->institute->id,
            'is_customer' => true,
            'is_vendor' => false,
        ]);
        $this->assertEquals('customer', $party->party_type);

        $party->update(['is_vendor' => true]);
        $this->assertEquals('both', $party->fresh()->party_type);
    }

    /** @test */
    public function customer_scope_returns_only_customers()
    {
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => false]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => false, 'is_vendor' => true]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => true]);

        $count = Party::customers()->where('institute_id', $this->institute->id)->count();
        $this->assertEquals(2, $count);
    }

    /** @test */
    public function supplier_scope_returns_only_vendors()
    {
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => false]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => false, 'is_vendor' => true]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => true]);

        $count = Party::suppliers()->where('institute_id', $this->institute->id)->count();
        $this->assertEquals(2, $count);
    }

    /** @test */
    public function both_scope_returns_only_both()
    {
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => false]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => false, 'is_vendor' => true]);
        Party::factory()->create(['institute_id' => $this->institute->id, 'is_customer' => true, 'is_vendor' => true]);

        $count = Party::both()->where('institute_id', $this->institute->id)->count();
        $this->assertEquals(1, $count);
    }
}