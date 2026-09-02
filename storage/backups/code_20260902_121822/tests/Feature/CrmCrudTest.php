<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\CrmContactService;
use App\Services\CrmLeadService;
use App\Services\CrmOrganizationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 31 — CRM Core: CRUD, search/filter/pagination, assignment, soft delete
 * and service-level duplicate protection for contacts, organizations and leads.
 */
class CrmCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Crm Inst'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return $this->user($institute, 'institute-owner', 'owner');
    }

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'email' => 'rahim'.uniqid().'@example.com',
            'phone' => '01811'.rand(100000, 999999),
            'is_customer' => true,
            'status' => 'active',
        ], $overrides);
    }

    private function orgPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme '.uniqid(),
            'email' => 'acme'.uniqid().'@example.com',
            'phone' => '01911'.rand(100000, 999999),
            'status' => 'active',
        ], $overrides);
    }

    private function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Karim',
            'last_name' => 'Ahmed',
            'email' => 'karim'.uniqid().'@example.com',
            'phone' => '01611'.rand(100000, 999999),
        ], $overrides);
    }

    // -------------------------------------------------------------- Contacts

    public function test_owner_can_create_contact(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('crm.contacts.store'), $this->contactPayload(['first_name' => 'Rahim']));

        $response->assertRedirect();
        $this->assertDatabaseHas('crm_contacts', [
            'institute_id' => $c->id,
            'first_name' => 'Rahim',
            'is_customer' => 1,
        ]);
    }

    public function test_contact_is_visible_and_searchable_on_index(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $target = $this->contactPayload(['email' => 'unique-scan@example.com']);

        $this->actingAs($owner, 'institute_user')->post(route('crm.contacts.store'), $target);
        $this->actingAs($owner, 'institute_user')->post(route('crm.contacts.store'), $this->contactPayload());

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.index', ['q' => 'unique-scan']));

        $response->assertOk();
        $contacts = $response->viewData('contacts');
        $this->assertSame(1, $contacts->total());
        $this->assertSame('unique-scan@example.com', $contacts->first()->email);
    }

    public function test_contact_index_filters_by_customer_and_status(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $this->actingAs($owner, 'institute_user')->post(route('crm.contacts.store'), $this->contactPayload(['is_customer' => true]));
        $this->actingAs($owner, 'institute_user')->post(route('crm.contacts.store'), $this->contactPayload(['is_customer' => false, 'status' => 'inactive']));

        $customers = $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.index', ['is_customer' => 1]))
            ->viewData('contacts');

        $this->assertSame(1, $customers->total());
        $this->assertTrue((bool) $customers->first()->is_customer);
    }

    public function test_contact_update_changes_fields(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $contact = $this->createContact($c, $owner);

        $response = $this->actingAs($owner, 'institute_user')
            ->put(route('crm.contacts.update', $contact), $this->contactPayload([
                'first_name' => 'Updated',
                'email' => $contact->email,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('crm_contacts', ['id' => $contact->id, 'first_name' => 'Updated']);
    }

    public function test_contact_soft_delete_hides_from_index_and_keeps_row(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $contact = $this->createContact($c, $owner);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.contacts.destroy', $contact))
            ->assertRedirect();

        $this->assertNotNull($contact->fresh()->deleted_at);
        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.index'))
            ->assertDontSee($contact->email);

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.show', $contact))
            ->assertNotFound();
    }

    public function test_duplicate_contact_email_is_rejected_on_create(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $payload = $this->contactPayload(['email' => 'dup@example.com']);

        $this->actingAs($owner, 'institute_user')->post(route('crm.contacts.store'), $payload)->assertRedirect();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('crm.contacts.store'), $payload)
            ->assertStatus(422);

        $this->assertSame(1, CrmContact::query()->where('email', 'dup@example.com')->count());
    }

    public function test_duplicate_contact_email_is_rejected_on_update(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $first = $this->createContact($c, $owner, ['email' => 'first@example.com']);
        $second = $this->createContact($c, $owner, ['email' => 'second@example.com']);

        $this->actingAs($owner, 'institute_user')
            ->putJson(route('crm.contacts.update', $first), $this->contactPayload(['email' => 'second@example.com']))
            ->assertStatus(422);
    }

    public function test_contact_assignment_updates_assigned_user(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $manager = $this->user($c, 'branch-manager', 'manager');
        $contact = $this->createContact($c, $owner);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.contacts.assign', $contact), ['assigned_user_id' => $manager->id])
            ->assertRedirect();

        $this->assertSame((int) $contact->fresh()->assigned_user_id, (int) $manager->id);
    }

    public function test_contact_index_paginates(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        for ($i = 0; $i < 21; $i++) {
            $this->createContact($c, $owner);
        }

        $response = $this->actingAs($owner, 'institute_user')->get(route('crm.contacts.index'));
        $response->assertOk();
        $this->assertSame(21, $response->viewData('contacts')->total());
        $this->assertSame(20, $response->viewData('contacts')->count());
    }

    // --------------------------------------------------------- Organizations

    public function test_owner_can_create_organization(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('crm.organizations.store'), $this->orgPayload(['name' => 'Globex']));

        $response->assertRedirect();
        $this->assertDatabaseHas('crm_organizations', ['institute_id' => $c->id, 'name' => 'Globex']);
    }

    public function test_duplicate_organization_name_is_rejected_case_insensitively(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.organizations.store'), $this->orgPayload(['name' => 'Acme Corp']))
            ->assertRedirect();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('crm.organizations.store'), $this->orgPayload(['name' => 'acme corp']))
            ->assertStatus(422);
    }

    public function test_organization_update_and_soft_delete(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $organization = $this->createOrganization($c, $owner);

        $this->actingAs($owner, 'institute_user')
            ->put(route('crm.organizations.update', $organization), $this->orgPayload(['name' => 'Updated Co', 'email' => $organization->email]))
            ->assertRedirect();

        $this->assertSame('Updated Co', $organization->fresh()->name);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.organizations.destroy', $organization))
            ->assertRedirect();

        $this->assertNotNull($organization->fresh()->deleted_at);
        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.organizations.show', $organization))
            ->assertNotFound();
    }

    public function test_organization_is_searchable_on_index(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $this->createOrganization($c, $owner, ['name' => 'NeedleOrg']);
        $this->createOrganization($c, $owner, ['name' => 'HaystackOrg']);

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('crm.organizations.index', ['q' => 'Needle']));

        $this->assertSame(1, $response->viewData('organizations')->total());
    }

    // ---------------------------------------------------------------- Leads

    public function test_owner_can_create_lead_defaulting_to_new_status(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('crm.leads.store'), $this->leadPayload(['first_name' => 'Karim']));

        $response->assertRedirect();

        $lead = CrmLead::query()->where('institute_id', $c->id)->firstOrFail();
        $this->assertSame('Karim', $lead->first_name);
        $this->assertSame(
            CrmLeadStatus::where('is_default', true)->value('id')
                ?? CrmLeadStatus::where('slug', 'new')->value('id'),
            $lead->status_id
        );
    }

    public function test_duplicate_lead_email_is_rejected(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $payload = $this->leadPayload(['email' => 'leaddup@example.com']);

        $this->actingAs($owner, 'institute_user')->post(route('crm.leads.store'), $payload)->assertRedirect();

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('crm.leads.store'), $payload)
            ->assertStatus(422);
    }

    public function test_lead_can_be_updated_to_new_status(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $lead = $this->createLead($c, $owner);

        $won = CrmLeadStatus::where('slug', 'won')->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->put(route('crm.leads.update', $lead), $this->leadPayload([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'status_id' => $won->id,
            ]))
            ->assertRedirect();

        $this->assertSame((int) $lead->fresh()->status_id, (int) $won->id);
    }

    public function test_lead_convert_creates_contact_and_marks_won(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $lead = $this->createLead($c, $owner, ['email' => 'convertme@example.com']);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('crm.leads.convert', $lead));

        $response->assertRedirect();
        $lead->refresh();

        $this->assertNotNull($lead->converted_at);
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertSame(CrmLeadStatus::where('slug', 'won')->value('id'), $lead->status_id);

        $contact = $lead->convertedContact()->first();
        $this->assertNotNull($contact);
        $this->assertSame('convertme@example.com', $contact->email);
        $this->assertTrue((bool) $contact->is_customer);
    }

    public function test_lead_soft_delete(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);
        $lead = $this->createLead($c, $owner);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.leads.destroy', $lead))
            ->assertRedirect();

        $this->assertNotNull($lead->fresh()->deleted_at);
    }

    // ------------------------------------------------------------ Audit trail

    public function test_create_contact_writes_audit_log(): void
    {
        $c = $this->institute();
        $owner = $this->owner($c);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.contacts.store'), $this->contactPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c->id,
            'user_type' => 'institute_user',
            'user_id' => $owner->id,
            'module' => 'crm',
            'action' => 'created',
        ]);
    }

    // ------------------------------------------------------------ Helpers

    private function createContact(Institute $institute, InstituteUser $actor, array $overrides = []): CrmContact
    {
        $payload = $this->contactPayload($overrides);

        return app(CrmContactService::class)->create(
            $payload,
            $institute->id,
            null,
            (int) $actor->id
        );
    }

    private function createOrganization(Institute $institute, InstituteUser $actor, array $overrides = []): CrmOrganization
    {
        $payload = $this->orgPayload($overrides);

        return app(CrmOrganizationService::class)->create(
            $payload,
            $institute->id,
            null,
            (int) $actor->id
        );
    }

    private function createLead(Institute $institute, InstituteUser $actor, array $overrides = []): CrmLead
    {
        $payload = $this->leadPayload($overrides);

        return app(CrmLeadService::class)->create(
            $payload,
            $institute->id,
            null,
            (int) $actor->id
        );
    }
}
