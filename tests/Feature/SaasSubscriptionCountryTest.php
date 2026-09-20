<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Institute;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-2 (B108): bKash gating is FK-based (countries.iso2),
 * not country-name-string based.
 *
 * - Renamed BD row + FK still allowed (rename-proof).
 * - Non-BD FK rejected with the verbatim 'country' message.
 * - Legacy null-FK + 'Bangladesh' string still allowed (covered by
 *   SaasCheckoutTest; asserted once here as the fallback pin).
 */
class SaasSubscriptionCountryTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug)=?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(string $countryName, ?int $countryId, string $package = 'FREE'): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = $this->package($package);

        return Institute::create([
            'name' => 'SaasCntry '.uniqid(),
            'slug' => 'saascntry-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => $countryName,
            'country_id' => $countryId,
        ]);
    }

    private function user(Institute $inst)
    {
        $role = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();

        return \App\Models\InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'saascntry-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    private function bdId(): int
    {
        return (int) Country::where('iso2', 'BD')->firstOrFail()->id;
    }

    public function test_bkash_gating_uses_fk_not_country_name(): void
    {
        // Rename the BD row in-test (rolled back): the FK still gates.
        \Illuminate\Support\Facades\DB::table('countries')->where('id', $this->bdId())->update(['name' => 'Bangla (Renamed)']);

        $inst = $this->institute('Bangla (Renamed)', $this->bdId(), 'FREE');
        $u = $this->user($inst);
        $pkg = $this->package('BASIC');

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect();
        $this->assertDatabaseHas('online_payment_attempts', ['institute_id' => $inst->id]);
    }

    public function test_non_bd_country_id_rejected(): void
    {
        $inId = (int) Country::where('iso2', 'IN')->firstOrFail()->id;

        $inst = $this->institute('India', $inId, 'FREE');
        $u = $this->user($inst);
        $pkg = $this->package('BASIC');

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertSessionHasErrors('country');
    }

    public function test_legacy_null_fk_bangladesh_string_still_allowed(): void
    {
        $inst = $this->institute('Bangladesh', null, 'FREE');
        $u = $this->user($inst);
        $pkg = $this->package('BASIC');

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect();
    }
}
