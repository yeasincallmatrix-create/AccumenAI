<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmContact;
use App\Models\CrmNote;
use App\Models\CrmTask;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\CrmContactService;
use App\Services\CrmLeadService;
use App\Services\CrmTaskService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 31 — CRM Core: activity timeline, notes and follow-up tasks attached to
 * contacts / organizations / leads, plus the task lifecycle (toggle, delete).
 */
class CrmTimelineTest extends TestCase
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

    private function institute(): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'Timeline Inst',
            'slug' => str()->slug('timeline-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
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

    private function contact(Institute $institute, int $actorId): CrmContact
    {
        return app(CrmContactService::class)->create([
            'first_name' => 'Timeline',
            'last_name' => 'Contact',
            'email' => 'timeline'.uniqid().'@example.com',
        ], $institute->id, null, $actorId);
    }

    // ------------------------------------------------------------- Activities

    public function test_activity_can_be_logged_on_contact_and_shows_in_timeline(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $contact = $this->contact($institute, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.activities.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'call',
                'summary' => 'Discussed renewal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('crm_activities', [
            'institute_id' => $institute->id,
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'call',
            'summary' => 'Discussed renewal',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.show', $contact))
            ->assertSee('Discussed renewal');
    }

    public function test_activity_rejects_foreign_subject(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $otherInstitute = $this->institute();
        $foreignContact = $this->contact($otherInstitute, (int) $this->user($otherInstitute, 'institute-owner', 'other')->id);

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('crm.activities.store'), [
                'subject_type' => 'contact',
                'subject_id' => $foreignContact->id,
                'summary' => 'Should not persist',
            ])
            ->assertStatus(404);
    }

    public function test_activity_invalid_type_is_rejected(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $contact = $this->contact($institute, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.activities.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'nonsense',
                'summary' => 'Bad type',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_activity_feed_lists_recent_activities(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $contact = $this->contact($institute, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.activities.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'meeting',
                'summary' => 'Site visit',
            ])
            ->assertRedirect();

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.activities.index'))
            ->assertOk()
            ->assertSee('Site visit');
    }

    // ---------------------------------------------------------------- Notes

    public function test_note_can_be_added_and_removed(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $contact = $this->contact($institute, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.notes.store'), [
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'body' => 'Prefers calls in the evening.',
            ])
            ->assertRedirect();

        $note = CrmNote::query()->where('subject_type', 'contact')->where('subject_id', $contact->id)->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.contacts.show', $contact))
            ->assertSee('Prefers calls in the evening.');

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.notes.destroy', $note))
            ->assertRedirect();

        $this->assertNotNull($note->fresh()->deleted_at);
    }

    // ---------------------------------------------------------------- Tasks

    public function test_standalone_task_can_be_created_toggled_and_deleted(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.tasks.store'), [
                'title' => 'Follow up with vendor',
                'priority' => 'high',
                'due_at' => now()->addDays(2)->format('Y-m-d H:i'),
            ])
            ->assertRedirect();

        $task = CrmTask::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->assertNull($task->subject_type);
        $this->assertSame('high', $task->priority);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.tasks.toggle', $task))
            ->assertRedirect();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('crm.tasks.destroy', $task))
            ->assertRedirect();

        $this->assertNotNull($task->fresh()->deleted_at);
    }

    public function test_task_attached_to_lead_appears_on_lead_page(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $lead = app(CrmLeadService::class)->create([
            'first_name' => 'Task',
            'last_name' => 'Lead',
            'email' => 'tasklead'.uniqid().'@example.com',
        ], $institute->id, null, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('crm.tasks.store'), [
                'subject_type' => 'lead',
                'subject_id' => $lead->id,
                'title' => 'Send proposal',
                'due_at' => now()->addDay()->format('Y-m-d H:i'),
            ])
            ->assertRedirect();

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.leads.show', $lead))
            ->assertSee('Send proposal');
    }

    public function test_task_requires_valid_subject(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $this->actingAs($owner, 'institute_user')
            ->postJson(route('crm.tasks.store'), [
                'subject_type' => 'contact',
                'subject_id' => 999999,
                'title' => 'Phantom',
            ])
            ->assertStatus(404);
    }

    public function test_tasks_index_lists_open_and_completed(): void
    {
        $institute = $this->institute();
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $open = app(CrmTaskService::class)->create([
            'title' => 'Open task',
        ], $institute->id, null, (int) $owner->id);

        app(CrmTaskService::class)->create([
            'title' => 'Done task',
            'status' => 'completed',
        ], $institute->id, null, (int) $owner->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.tasks.index'))
            ->assertOk()
            ->assertSee('Open task')
            ->assertDontSee('Done task');

        $this->actingAs($owner, 'institute_user')
            ->get(route('crm.tasks.index', ['status' => 'completed']))
            ->assertSee('Done task');
    }
}
