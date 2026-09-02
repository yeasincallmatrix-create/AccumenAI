<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Mail\NotificationMail;
use App\Models\Batch;
use App\Models\Country;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Education\BatchLifecycleService;
use App\Services\Notification\NotificationRecipientResolver;
use App\Services\Notification\NotificationService;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * STEP 45 — Notifications / SMS / Email engine.
 *
 * Covers template rendering, recipient resolution, channel routing (config ∩
 * template ∩ institute toggles ∩ user preferences), in-app delivery into the
 * existing notifications table, email + SMS delivery, retry scheduling and the
 * settings UI permission matrix.
 */
class NotificationEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private ?Country $countryCache = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('notifications:seed-templates');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();

        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function country(): Country
    {
        return $this->countryCache ??= Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BDD',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function institute(string $name = 'Notify Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => 'Bangladesh',
            'country_id' => $this->country()->id,
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function staff(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'staff-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, array $attrs = []): Student
    {
        return Student::create(array_merge([
            'institute_id' => $institute->id,
            'student_id_number' => Student::nextStudentNumber($institute->id),
            'first_name' => 'Rahim',
            'last_name' => 'Student',
            'email' => 'student-'.uniqid().'@example.test',
            'phone' => '01800'.rand(100000, 999999),
            'guardian_phone' => '01900'.rand(100000, 999999),
            'registration_number' => 'REG-'.mt_rand(10000, 99999),
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ], $attrs));
    }

    private function course(): Course
    {
        return Course::create([
            'institute_id' => null,
            'course_code' => 'C'.mt_rand(100000, 999999),
            'name' => 'Catalog Course '.mt_rand(1000, 9999),
            'status' => 'active',
        ]);
    }

    private function batch(Institute $institute): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $this->course()->id,
            'name' => 'Batch '.mt_rand(1000, 9999),
            'batch_code' => 'B'.mt_rand(100000, 999999),
            'shift' => 'day',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'seat_capacity' => 30,
            'seat_filled' => 0,
            'status' => 'upcoming',
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(100000, 999999),
            'enrollment_date' => now()->toDateString(),
            'fee_payable' => 0,
            'discount' => 0,
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- Renderer

    public function test_renderer_replaces_placeholders_with_and_without_spaces(): void
    {
        $renderer = app(NotificationTemplateRenderer::class);

        $this->assertSame(
            'Hello Rahim, course CSE.',
            $renderer->render('Hello {{student_name}}, course {{ course_name }}.', [
                'student_name' => 'Rahim',
                'course_name' => 'CSE',
            ])
        );
    }

    public function test_renderer_strips_missing_tokens(): void
    {
        $renderer = app(NotificationTemplateRenderer::class);

        $this->assertSame('Hello Rahim, course .', $renderer->render('Hello {{student_name}}, course {{missing_var}}.', [
            'student_name' => 'Rahim',
        ]));
    }

    // ------------------------------------------------------------- Resolver

    public function test_resolver_uses_guardian_phone_for_student_sms(): void
    {
        $inst = $this->institute();
        $student = $this->student($inst);

        $resolved = app(NotificationRecipientResolver::class)->resolve($student);

        $this->assertSame('student', $resolved['recipient_type']);
        $this->assertSame((int) $student->id, $resolved['recipient_id']);
        $this->assertSame($student->email, $resolved['email']);
        $this->assertSame($student->guardian_phone, $resolved['phone']);
        $this->assertSame((int) $inst->id, $resolved['institute_id']);
    }

    public function test_resolver_resolves_institute_user_and_external_contacts(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);

        $resolver = app(NotificationRecipientResolver::class);

        $userResolved = $resolver->resolve($user);
        $this->assertSame('institute_user', $userResolved['recipient_type']);

        $external = $resolver->resolve(['email' => 'someone@example.com', 'institute_id' => $inst->id]);
        $this->assertSame('external_email', $external['recipient_type']);
        $this->assertSame('someone@example.com', $external['email']);
        $this->assertSame((int) $inst->id, $external['institute_id']);
    }

    // ------------------------------------------------------------- Engine

    public function test_unknown_event_is_ignored(): void
    {
        $inst = $this->institute();
        TenantContext::set($inst->id);

        app(NotificationService::class)->send('module.nonexistent', $this->student($inst));

        $this->assertSame(0, NotificationLog::query()->where('institute_id', $inst->id)->count());
    }

    public function test_send_to_student_routes_all_channels_and_marks_sent(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        app(NotificationService::class)->send('education.student_enrolled', $student, [
            'student_name' => $student->full_name,
            'registration_number' => $student->registration_number,
            'course_name' => 'Web Development',
            'batch_name' => 'Batch A',
        ], ['actor_type' => 'institute_user', 'actor_id' => 1]);

        $logs = NotificationLog::query()->where('event', 'education.student_enrolled')->get();

        $this->assertCount(3, $logs);
        $this->assertEqualsCanonicalizing(['in_app', 'sms', 'email'], $logs->pluck('channel')->all());

        foreach ($logs as $log) {
            $this->assertSame('sent', $log->status);
            $this->assertNotNull($log->sent_at);
            $this->assertSame((int) $inst->id, (int) $log->institute_id);
        }

        $sms = $logs->firstWhere('channel', 'sms');
        $this->assertSame('log', $sms->provider);
        $this->assertStringStartsWith('log-', (string) $sms->provider_message_id);
        $this->assertSame($student->guardian_phone, $sms->recipient_contact);
        $this->assertStringContainsString($student->full_name, $sms->body);

        $email = $logs->firstWhere('channel', 'email');
        $this->assertSame($student->email, $email->recipient_contact);

        $inApp = $logs->firstWhere('channel', 'in_app');
        $this->assertNotNull($inApp->notification_id);

        $notification = Notification::query()->find($inApp->notification_id);
        $this->assertNotNull($notification);
        $this->assertSame('student', $notification->target_user_type);
        $this->assertSame((int) $student->id, (int) $notification->target_user_id);
        $this->assertSame('education.student_enrolled', $notification->category);
        $this->assertSame('institute_user', $notification->created_by_type);

        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_institute_override_template_takes_precedence(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        NotificationTemplate::create([
            'institute_id' => $inst->id,
            'event' => 'education.student_enrolled',
            'channel' => 'sms',
            'language' => 'en',
            'name' => 'Override',
            'subject' => '',
            'body' => 'OVERRIDE {{ student_name }}',
            'variables' => ['student_name'],
            'is_active' => true,
        ]);

        app(NotificationService::class)->send('education.student_enrolled', $student, [
            'student_name' => $student->full_name,
        ]);

        $sms = NotificationLog::query()->where('event', 'education.student_enrolled')->where('channel', 'sms')->first();

        $this->assertNotNull($sms);
        $this->assertStringContainsString('OVERRIDE', $sms->body);
        $this->assertSame((int) $inst->id, (int) $sms->institute_id);
    }

    public function test_missing_template_skips_channel(): void
    {
        $inst = $this->institute();
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        NotificationTemplate::query()->where('event', 'education.student_enrolled')->where('channel', 'sms')->delete();

        app(NotificationService::class)->send('education.student_enrolled', $student, []);

        $this->assertSame(0, NotificationLog::query()->where('channel', 'sms')->count());
        $this->assertSame(1, NotificationLog::query()->where('channel', 'in_app')->count());
    }

    public function test_user_preference_disables_a_channel(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        NotificationPreference::create([
            'recipient_type' => 'student',
            'recipient_id' => $student->id,
            'event' => 'education.student_enrolled',
            'channel' => 'email',
            'enabled' => false,
        ]);

        app(NotificationService::class)->send('education.student_enrolled', $student, []);

        $channels = NotificationLog::query()->where('event', 'education.student_enrolled')->pluck('channel')->all();

        $this->assertEqualsCanonicalizing(['in_app', 'sms'], $channels);
    }

    public function test_institute_master_toggle_disables_channel(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        InstituteSetting::updateOrCreate(
            ['institute_id' => $inst->id],
            ['notification_settings' => ['in_app' => false, 'email' => true, 'sms' => true]]
        );

        app(NotificationService::class)->send('education.student_enrolled', $student, []);

        $channels = NotificationLog::query()->where('event', 'education.student_enrolled')->pluck('channel')->all();

        $this->assertEqualsCanonicalizing(['sms', 'email'], $channels);
    }

    public function test_forged_institute_option_is_ignored_for_scoped_recipients(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $other = $this->institute('Other Inst');
        $student = $this->student($inst);
        TenantContext::set($inst->id);

        app(NotificationService::class)->send('education.student_enrolled', $student, [], [
            'institute_id' => $other->id,
        ]);

        $logs = NotificationLog::query()->where('event', 'education.student_enrolled')->get();
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertSame((int) $inst->id, (int) $log->institute_id);
        }
    }

    public function test_tenant_isolation_keeps_logs_apart(): void
    {
        Mail::fake();

        $instA = $this->institute('Inst A');
        $instB = $this->institute('Inst B');
        $studentA = $this->student($instA);
        $studentB = $this->student($instB);

        TenantContext::set($instA->id);
        app(NotificationService::class)->send('education.student_enrolled', $studentA, []);

        TenantContext::set($instB->id);
        app(NotificationService::class)->send('education.student_enrolled', $studentB, []);

        TenantContext::set($instA->id);
        $this->assertSame(3, NotificationLog::query()->where('institute_id', $instA->id)->count());

        TenantContext::set($instB->id);
        $this->assertSame(3, NotificationLog::query()->where('institute_id', $instB->id)->count());
    }

    // ------------------------------------------------------------- Retry

    public function test_retry_command_requeues_failed_logs_within_budget(): void
    {
        Queue::fake();

        $inst = $this->institute();
        $log = NotificationLog::create([
            'institute_id' => $inst->id,
            'event' => 'education.student_enrolled',
            'recipient_type' => 'student',
            'recipient_id' => 999,
            'recipient_contact' => '01700000000',
            'channel' => 'sms',
            'status' => NotificationLog::STATUS_FAILED,
            'subject' => '',
            'body' => 'hello',
            'queued_at' => now(),
            'max_retries' => 2,
            'retry_count' => 0,
            'error' => 'boom',
        ]);

        $this->artisan('notifications:retry')->assertSuccessful();

        $log->refresh();
        $this->assertSame(NotificationLog::STATUS_QUEUED, $log->status);
        $this->assertNull($log->error);

        Queue::assertPushed(SendNotificationJob::class, fn ($job) => $job->logId === $log->id);
    }

    public function test_retry_command_skips_exhausted_logs(): void
    {
        Queue::fake();

        $inst = $this->institute();
        $log = NotificationLog::create([
            'institute_id' => $inst->id,
            'event' => 'education.student_enrolled',
            'recipient_type' => 'student',
            'recipient_id' => 999,
            'recipient_contact' => '01700000000',
            'channel' => 'sms',
            'status' => NotificationLog::STATUS_FAILED,
            'subject' => '',
            'body' => 'hello',
            'queued_at' => now(),
            'max_retries' => 2,
            'retry_count' => 2,
            'error' => 'boom',
        ]);

        $this->artisan('notifications:retry')->assertSuccessful();

        $log->refresh();
        $this->assertSame(NotificationLog::STATUS_FAILED, $log->status);

        Queue::assertNotPushed(SendNotificationJob::class);
    }

    // ------------------------------------------------------------- Hooks

    public function test_enrolling_a_student_fires_the_student_enrolled_hook(): void
    {
        Mail::fake();

        $inst = $this->institute();
        $student = $this->student($inst);
        $batch = $this->batch($inst);
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        $lifecycle = app(BatchLifecycleService::class);
        $lifecycle->enrollStudent($student, $batch, ['enrollment_date' => now()->toDateString()], $inst->id, $owner->id);

        $logs = NotificationLog::query()->where('event', 'education.student_enrolled')->get();
        $this->assertNotEmpty($logs);

        $ownerLog = NotificationLog::query()
            ->where('event', 'education.student_enrolled')
            ->where('recipient_type', 'institute_user')
            ->where('recipient_id', $owner->id)
            ->first();
        $this->assertNotNull($ownerLog);
        $this->assertSame('in_app', $ownerLog->channel);
    }

    public function test_changing_batch_status_notifies_owners_in_app(): void
    {
        $inst = $this->institute();
        $batch = $this->batch($inst);
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        $lifecycle = app(BatchLifecycleService::class);
        $lifecycle->changeStatus($batch, 'ongoing', $owner->id);

        $log = NotificationLog::query()
            ->where('event', 'education.batch_status_changed')
            ->where('recipient_type', 'institute_user')
            ->where('recipient_id', $owner->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('in_app', $log->channel);
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString('ongoing', $log->body);
    }

    // ------------------------------------------------------------- UI

    public function test_template_override_requires_settings_manage_permission(): void
    {
        $inst = $this->institute();
        $staff = $this->staff($inst);
        TenantContext::set($inst->id);

        $this->actingAs($staff, 'institute_user')
            ->post(route('settings.notifications.templates.store'), [
                'event' => 'education.student_enrolled',
                'channel' => 'sms',
                'language' => 'en',
                'name' => 'x',
                'body' => 'x',
            ])
            ->assertForbidden();

        $this->assertSame(0, NotificationTemplate::query()->where('institute_id', $inst->id)->where('channel', 'sms')->count());
    }

    public function test_owner_can_create_and_list_an_override(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        $this->actingAs($owner, 'institute_user')
            ->post(route('settings.notifications.templates.store'), [
                'event' => 'education.student_enrolled',
                'channel' => 'sms',
                'language' => 'en',
                'name' => 'My SMS copy',
                'subject' => '',
                'body' => 'Custom {{ student_name }} message',
                'variables' => ['student_name'],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $template = NotificationTemplate::query()
            ->where('institute_id', $inst->id)
            ->where('event', 'education.student_enrolled')
            ->where('channel', 'sms')
            ->where('language', 'en')
            ->first();

        $this->assertNotNull($template);
        $this->assertTrue($template->is_active);

        $this->actingAs($owner, 'institute_user')
            ->get(route('settings.notifications.templates.index'))
            ->assertOk()
            ->assertSee('My SMS copy');
    }

    public function test_global_template_cannot_be_edited(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        $global = NotificationTemplate::query()
            ->whereNull('institute_id')
            ->where('event', 'education.student_enrolled')
            ->where('channel', 'sms')
            ->where('language', 'en')
            ->firstOrFail();

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.notifications.templates.update', $global), [
                'event' => 'education.student_enrolled',
                'channel' => 'sms',
                'language' => 'en',
                'name' => 'Hacked',
                'body' => 'Hacked',
            ])
            ->assertForbidden();
    }

    public function test_channel_toggle_updates_institute_settings(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);

        $this->actingAs($owner, 'institute_user')
            ->post(route('settings.notifications.channels'), [
                'in_app' => '1',
                'email' => '0',
                'sms' => '1',
            ])
            ->assertRedirect();

        $setting = InstituteSetting::query()->where('institute_id', $inst->id)->first();
        $this->assertIsArray($setting?->notification_settings);
        $this->assertTrue($setting->notification_settings['in_app']);
        $this->assertFalse($setting->notification_settings['email']);
        $this->assertTrue($setting->notification_settings['sms']);
    }
}
