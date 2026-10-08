<?php

namespace Tests\Feature;

use App\Models\HrDesignation;
use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Membership;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contract block on the doctor form: employment type, designation,
 * doctor fee share and the opt-in discount ceiling.
 */
class DoctorContractFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctorUser;

    private HrDesignation $designation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Contract Fields Institute',
            'slug' => 'contract-fields-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['advanced'])->value('id'),
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $this->institute->id,
            'package_id' => $this->institute->package_id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        $this->doctorUser = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->doctorUser->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-admin')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        $this->designation = HrDesignation::create([
            'institute_id' => $this->institute->id,
            'name' => 'Consultant Physician',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->doctorUser->id,
            'registration_number' => 'REG-C-'.strtoupper(uniqid()),
            'is_active' => 1,
        ], $extra);
    }

    public function test_contract_fields_persist_on_store(): void
    {
        $this->post(route('medical.doctors.store'), $this->payload([
            'employment_type' => 'contractual',
            'designation_id' => $this->designation->id,
            'doctor_fee_percentage' => 60,
            'allow_discount' => 1,
            'max_discount_percent' => 15,
        ]))->assertSessionHasNoErrors();

        $doctor = Doctor::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->assertSame('contractual', $doctor->employment_type);
        $this->assertSame($this->designation->id, (int) $doctor->designation_id);
        $this->assertSame(60.0, (float) $doctor->doctor_fee_percentage);
        $this->assertTrue((bool) $doctor->allow_discount);
        $this->assertSame(15.0, (float) $doctor->max_discount_percent);
        $this->assertSame($this->designation->name, $doctor->designation->name);
    }

    public function test_discount_ceiling_is_dropped_when_toggle_is_off(): void
    {
        $this->post(route('medical.doctors.store'), $this->payload([
            'max_discount_percent' => 25,
        ]))->assertSessionHasNoErrors();

        $doctor = Doctor::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->assertFalse((bool) $doctor->allow_discount);
        $this->assertNull($doctor->max_discount_percent);
    }

    public function test_foreign_designation_is_rejected(): void
    {
        $foreign = Institute::create([
            'name' => 'Contract Fields Foreign',
            'slug' => 'contract-fields-foreign-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $this->institute->package_id,
        ]);
        // Direct insert: the model's tenant guard would rewrite institute_id.
        $foreignDesignationId = DB::table('hr_designations')->insertGetId([
            'institute_id' => $foreign->id,
            'name' => 'Foreign Consultant',
            'is_active' => 1,
        ]);

        $this->post(route('medical.doctors.store'), $this->payload([
            'designation_id' => $foreignDesignationId,
        ]))->assertSessionHasErrors(['designation_id']);
    }

    public function test_fee_percentage_out_of_range_is_rejected(): void
    {
        $this->post(route('medical.doctors.store'), $this->payload([
            'doctor_fee_percentage' => 150,
        ]))->assertSessionHasErrors(['doctor_fee_percentage']);
    }

    public function test_create_page_renders_contract_block(): void
    {
        $this->get(route('medical.doctors.create'))
            ->assertOk()
            ->assertSee('Contract')
            ->assertSee('Employment Type')
            ->assertSee('Designation')
            ->assertSee('Doctor Fee %')
            ->assertSee($this->designation->name);
    }

    public function test_edit_page_prefills_contract_block(): void
    {
        $this->post(route('medical.doctors.store'), $this->payload([
            'employment_type' => 'part_time',
            'designation_id' => $this->designation->id,
            'doctor_fee_percentage' => 75,
            'allow_discount' => 1,
            'max_discount_percent' => 10,
        ]))->assertSessionHasNoErrors();

        $doctor = Doctor::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $html = $this->get(route('medical.doctors.edit', $doctor))->assertOk()->getContent();

        $this->assertStringContainsString('>Contract<', $html);
        $this->assertSame(1, preg_match('/<option value="part_time" selected/', $html));
        $this->assertSame(1, preg_match('/name="doctor_fee_percentage"[^>]*value="75(\.0+)?"/', $html));
        $this->assertSame(1, preg_match('/name="max_discount_percent"[^>]*value="10(\.0+)?"/', $html));
        $this->assertSame(1, preg_match('/id="allow_discount"[^>]*checked/', $html));
    }
}
