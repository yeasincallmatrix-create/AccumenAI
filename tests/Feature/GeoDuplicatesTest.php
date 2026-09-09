<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Services\GeoDuplicatesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 4: duplicate scan finds true dupes (never legitimate same-name /
 * different-parent rows); merge moves children and deletes losers;
 * safe-delete refuses referenced rows; cross-name merges are rejected.
 */
class GeoDuplicatesTest extends TestCase
{
    use DatabaseTransactions;

    private Country $country;

    private GeoDuplicatesService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = new GeoDuplicatesService();
        $this->country = Country::create(['name' => 'Dupland', 'iso2' => 'DU', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 1, 'name' => 'Division', 'slug' => 'du-div', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 2, 'name' => 'District', 'slug' => 'du-dis', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 3, 'name' => 'Upazila', 'slug' => 'du-upa', 'status' => true]);
    }

    private function div(): AdministrativeUnit
    {
        return AdministrativeUnit::create([
            'country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(1),
            'parent_id' => null, 'name' => 'Divvy', 'code' => 'DU-D', 'status' => true,
        ]);
    }

    private function lvl(int $n): int
    {
        return AdministrativeLevel::where('country_id', $this->country->id)->where('level_number', $n)->value('id');
    }

    public function test_scan_ignores_legitimate_repeats(): void
    {
        $d = $this->div();
        // Same upazila name in two DIFFERENT districts is legitimate.
        $d2 = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(1), 'parent_id' => null, 'name' => 'Divvy2', 'code' => 'DU-D2', 'status' => true]);
        foreach ([$d->id, $d2->id] as $i => $pid) {
            $dist = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $pid, 'name' => 'Dist'.$i, 'code' => 'DU-D'.$i, 'status' => true]);
            AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(3), 'parent_id' => $dist->id, 'name' => 'Kaliganj', 'code' => 'DU-K'.$i, 'status' => true]);
        }

        $scan = $this->svc->scan($this->country);

        $this->assertSame(0, $scan['name_group_count']);
        $this->assertSame(0, $scan['code_group_count']);
    }

    public function test_scan_finds_true_dupes(): void
    {
        $d = $this->div();
        AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $d->id, 'name' => 'Sameplace', 'code' => 'DU-A', 'status' => true]);
        AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $d->id, 'name' => 'Sameplace', 'code' => 'DU-B', 'status' => true]);

        $scan = $this->svc->scan($this->country);

        $this->assertSame(1, $scan['name_group_count']);
        $this->assertCount(2, $scan['name_groups'][0]['members']);
    }

    public function test_merge_moves_children_and_deletes_loser(): void
    {
        $d = $this->div();
        $a = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $d->id, 'name' => 'Sameplace', 'code' => 'DU-A', 'status' => true]);
        $b = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $d->id, 'name' => 'Sameplace', 'code' => 'DU-B', 'status' => true]);
        $kid = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(3), 'parent_id' => $b->id, 'name' => 'Kid', 'code' => 'DU-K', 'status' => true]);

        $result = $this->svc->merge($this->country, [$a->id, $b->id], $a->id);

        $this->assertSame($a->id, $result['survivor']);
        $this->assertSame(1, $result['merged']);
        $this->assertSame(1, $result['children_moved']);
        $this->assertSame($a->id, $kid->fresh()->parent_id);
        $this->assertNull(AdministrativeUnit::find($b->id));
    }

    public function test_delete_blocked_with_children(): void
    {
        $d = $this->div();
        AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(2), 'parent_id' => $d->id, 'name' => 'Kid', 'code' => 'DU-K', 'status' => true]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->destroyRow($this->country, $d->id);
    }

    public function test_cross_name_merge_rejected(): void
    {
        $d = $this->div();
        $e = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $this->lvl(1), 'parent_id' => null, 'name' => 'Elsewhere', 'code' => 'DU-E', 'status' => true]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->svc->merge($this->country, [$d->id, $e->id]);
    }
}
