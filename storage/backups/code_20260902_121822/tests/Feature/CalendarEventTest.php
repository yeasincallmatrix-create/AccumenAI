<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Room;
use App\Models\Subject;
use App\Services\CalendarEventService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 50 — Calendar Events, Timetable & Recurring Scheduling.
 *
 * Covers: CRUD, conflict detection (teacher/room/batch), recurrence generation,
 * timetable views, date-range queries, search, filtering, multi-tenant isolation,
 * event types, all-day events, reminders, status management.
 */
class CalendarEventTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function seededContext(): array
    {
        $country = Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);

        $institute = Institute::create([
            'name' => 'Calendar Institute',
            'slug' => str()->slug('calendar-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Main Campus', 'status' => 'active']);
        $branch2 = Branch::create(['institute_id' => $institute->id, 'name' => 'North Campus', 'status' => 'active']);

        $manager = $this->instituteUser($institute, $branch->id, 'branch-manager', 'Branch', 'Manager');
        $teacher1 = $this->instituteUser($institute, $branch->id, 'teacher', 'Alice', 'Teacher');
        $teacher2 = $this->instituteUser($institute, $branch->id, 'teacher', 'Bob', 'Teacher');

        $course = Course::create([
            'institute_id' => $institute->id,
            'course_code' => 'CAL'.mt_rand(1000, 9999),
            'name' => 'Web Development',
            'slug' => 'web-dev-'.uniqid(),
            'category_id' => CourseCategory::create([
                'name' => 'Tech',
                'slug' => 'tech-'.uniqid(),
                'institute_id' => $institute->id,
                'status' => 'active',
            ])->id,
            'fee' => 10000,
            'status' => 'active',
        ]);

        $batch = Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'teacher_id' => $teacher1->id,
            'name' => 'Batch A',
            'batch_code' => 'BA'.mt_rand(100, 999),
            'shift' => 'morning',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => 'ongoing',
        ]);

        $batch2 = Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'branch_id' => $branch2->id,
            'teacher_id' => $teacher2->id,
            'name' => 'Batch B',
            'batch_code' => 'BB'.mt_rand(100, 999),
            'shift' => 'evening',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => 'ongoing',
        ]);

        $room = Room::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'name' => 'Room 101',
            'capacity' => 40,
            'status' => 'active',
        ]);

        $room2 = Room::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'name' => 'Room 102',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'category_id' => $course->category_id,
            'subject_type' => 'professional',
            'subject_code' => 'WD101',
            'name' => 'HTML & CSS',
            'slug' => 'html-css-'.uniqid(),
            'status' => 'active',
        ]);

        $academicYear = AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => '2026',
            'code' => 'AY2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => true,
        ]);

        $institute2 = Institute::create([
            'name' => 'Foreign Institute',
            'slug' => str()->slug('foreign-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
        $branchForeign = Branch::create(['institute_id' => $institute2->id, 'name' => 'Foreign Branch', 'status' => 'active']);
        $foreignManager = $this->instituteUser($institute2, $branchForeign->id, 'branch-manager', 'Foreign', 'Manager');

        return compact(
            'institute', 'branch', 'branch2', 'manager', 'teacher1', 'teacher2',
            'course', 'batch', 'batch2', 'room', 'room2', 'subject', 'academicYear',
            'institute2', 'branchForeign', 'foreignManager'
        );
    }

    private function instituteUser(Institute $institute, ?int $branchId, string $roleSlug, string $first, string $last): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($roleSlug).'-'.$first.'-'.uniqid().'@example.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Lecture 1',
            'event_type' => 'class',
            'start_date' => '2026-03-15',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'is_all_day' => false,
        ], $overrides);
    }

    // --------------------------------------------------------- Tests

    /** @test */
    public function manager_can_create_calendar_event(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);
        BranchContext::set($ctx['branch']->id);

        $payload = $this->eventPayload([
            'batch_id' => $ctx['batch']->id,
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'subject_id' => $ctx['subject']->id,
            'course_id' => $ctx['course']->id,
        ]);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'institute_id' => $ctx['institute']->id,
            'title' => 'Lecture 1',
            'event_type' => 'class',
        ]);
    }

    /** @test */
    public function can_create_all_day_event(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $payload = $this->eventPayload([
            'title' => 'Holiday',
            'event_type' => 'holiday',
            'start_date' => '2026-12-25',
            'end_date' => '2026-12-25',
            'is_all_day' => true,
            'start_time' => null,
            'end_time' => null,
        ]);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Holiday',
            'is_all_day' => true,
        ]);
    }

    /** @test */
    public function can_create_event_with_recurrence(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $payload = $this->eventPayload([
            'title' => 'Daily Lecture',
            'event_type' => 'class',
            'start_date' => '2026-03-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_rule' => [
                'frequency' => 'daily',
                'interval' => 1,
                'end_date' => '2026-03-05',
            ],
        ]);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), $payload);

        $response->assertRedirect();
        $master = CalendarEvent::where('title', 'Daily Lecture')->whereNull('parent_event_id')->first();
        $this->assertNotNull($master);
        $this->assertNotNull($master->recurrence_rule);

        $children = CalendarEvent::where('parent_event_id', $master->id)->get();
        $this->assertGreaterThan(0, $children->count());
    }

    /** @test */
    public function can_create_weekly_recurrence_with_days_of_week(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $payload = $this->eventPayload([
            'title' => 'Weekly Class',
            'event_type' => 'class',
            'start_date' => '2026-03-02', // Monday
            'start_time' => '14:00',
            'end_time' => '15:00',
            'recurrence_rule' => [
                'frequency' => 'weekly',
                'interval' => 1,
                'days_of_week' => ['mon', 'wed', 'fri'],
                'end_date' => '2026-03-20',
            ],
        ]);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), $payload);

        $response->assertRedirect();
        $master = CalendarEvent::where('title', 'Weekly Class')->whereNull('parent_event_id')->first();
        $children = CalendarEvent::where('parent_event_id', $master->id)->get();

        // 2026-03-02 Mon (master), 04 Wed, 06 Fri, 09 Mon, 11 Wed, 13 Fri, 16 Mon, 18 Wed, 20 Fri = 8 children
        $this->assertGreaterThanOrEqual(8, $children->count());
    }

    /** @test */
    public function can_create_monthly_recurrence(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $payload = $this->eventPayload([
            'title' => 'Monthly Exam',
            'event_type' => 'exam',
            'start_date' => '2026-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'recurrence_rule' => [
                'frequency' => 'monthly',
                'interval' => 1,
                'end_date' => '2026-06-30',
            ],
        ]);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), $payload);

        $response->assertRedirect();
        $master = CalendarEvent::where('title', 'Monthly Exam')->whereNull('parent_event_id')->first();
        $children = CalendarEvent::where('parent_event_id', $master->id)->get();

        // Jan 15 to Jun 15 = 6 occurrences (master) + 5 children
        $this->assertGreaterThanOrEqual(5, $children->count());
    }

    /** @test */
    public function teacher_conflict_detected_on_create(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        // First event: teacher1 at 09:00-10:30
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Morning Class',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '09:00',
            'end_time' => '10:30',
        ]));

        // Second event: same teacher, same time = conflict
        $response = $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Overlapping Class',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room2']->id,
            'batch_id' => $ctx['batch2']->id,
            'start_time' => '09:30',
            'end_time' => '11:00',
        ]));

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Overlapping Class']);
    }

    /** @test */
    public function room_conflict_detected_on_create(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        // First event: room1 at 14:00-15:00
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Afternoon Session',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]));

        // Second event: same room, overlapping time
        $response = $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Room Conflict',
            'teacher_id' => $ctx['teacher2']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch2']->id,
            'start_time' => '14:30',
            'end_time' => '15:30',
        ]));

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Room Conflict']);
    }

    /** @test */
    public function batch_conflict_detected_on_create(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        // First event: batch1 at 11:00-12:00
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Batch Session',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]));

        // Second event: same batch, overlapping time
        $response = $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Batch Double Book',
            'teacher_id' => $ctx['teacher2']->id,
            'room_id' => $ctx['room2']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '11:30',
            'end_time' => '12:30',
        ]));

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Batch Double Book']);
    }

    /** @test */
    public function no_conflict_when_times_do_not_overlap(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        // First event: 09:00-10:00
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'First Class',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));

        // Second event: 10:00-11:00 — no overlap (adjacent is OK)
        $response = $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Second Class',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', ['title' => 'Second Class']);
    }

    /** @test */
    public function can_update_event(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Original Title',
            'teacher_id' => $ctx['teacher1']->id,
        ]));

        $event = CalendarEvent::where('title', 'Original Title')->first();

        $response = $this->actingAs($ctx['manager'])
            ->put(route('calendar.events.update', $event), ['title' => 'Updated Title', 'event_type' => 'exam']);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'title' => 'Updated Title']);
    }

    /** @test */
    public function can_delete_event_and_cascade_children(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Recurring Delete Test',
            'start_date' => '2026-04-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_rule' => ['frequency' => 'daily', 'interval' => 1, 'end_date' => '2026-04-05'],
        ]));

        $master = CalendarEvent::where('title', 'Recurring Delete Test')->whereNull('parent_event_id')->first();
        $childCount = CalendarEvent::where('parent_event_id', $master->id)->count();
        $this->assertGreaterThan(0, $childCount);

        $response = $this->actingAs($ctx['manager'])
            ->delete(route('calendar.events.destroy', $master));

        $response->assertRedirect();
        $this->assertSoftDeleted('calendar_events', ['id' => $master->id]);
        $this->assertDatabaseMissing('calendar_events', ['parent_event_id' => $master->id, 'deleted_at' => null]);
    }

    /** @test */
    public function can_show_event_detail(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Detail View Test',
            'description' => 'Test description',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'batch_id' => $ctx['batch']->id,
            'subject_id' => $ctx['subject']->id,
            'course_id' => $ctx['course']->id,
        ]));

        $event = CalendarEvent::where('title', 'Detail View Test')->first();

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.events.show', $event));

        $response->assertOk();
        $response->assertSee('Detail View Test');
    }

    /** @test */
    public function events_json_api_returns_formatted_events(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'API Test Event',
            'start_date' => '2026-05-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->getJson(route('calendar.events.json', ['start' => '2026-05-01', 'end' => '2026-05-31']));

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $titles = array_column($data, 'title');
        $this->assertContains('API Test Event', $titles);
    }

    /** @test */
    public function events_json_includes_color_by_type(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Exam Event',
            'event_type' => 'exam',
            'start_date' => '2026-05-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->getJson(route('calendar.events.json', ['start' => '2026-05-01', 'end' => '2026-05-31']));

        $data = $response->json();
        $examEvent = collect($data)->firstWhere('title', 'Exam Event');
        $this->assertEquals('#dc3545', $examEvent['color']);
    }

    /** @test */
    public function search_events_by_title(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Physics Practical Session',
        ]));
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Chemistry Lecture',
            'start_date' => '2026-03-16',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->getJson(route('calendar.events.search', ['q' => 'Physics']));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Physics Practical Session', $data[0]['title']);
    }

    /** @test */
    public function search_events_by_teacher_name(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Alice Lecture',
            'teacher_id' => $ctx['teacher1']->id,
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->getJson(route('calendar.events.search', ['q' => 'Alice']));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    /** @test */
    public function timetable_returns_events_grouped_by_date(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Mon Class',
            'start_date' => '2026-03-16', // Monday
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Tue Class',
            'start_date' => '2026-03-17', // Tuesday
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.timetable', ['start_date' => '2026-03-16', 'end_date' => '2026-03-22']));

        $response->assertOk();
        $response->assertSee('Mon Class');
        $response->assertSee('Tue Class');
    }

    /** @test */
    public function timetable_can_filter_by_batch(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Batch A Event',
            'batch_id' => $ctx['batch']->id,
            'start_date' => '2026-03-16',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Batch B Event',
            'batch_id' => $ctx['batch2']->id,
            'start_date' => '2026-03-16',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.timetable', [
                'start_date' => '2026-03-16',
                'end_date' => '2026-03-22',
                'batch_id' => $ctx['batch']->id,
            ]));

        $response->assertOk();
        $response->assertSee('Batch A Event');
        $response->assertDontSee('Batch B Event');
    }

    /** @test */
    public function timetable_can_filter_by_teacher(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Alice Class',
            'teacher_id' => $ctx['teacher1']->id,
            'start_date' => '2026-03-16',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Bob Class',
            'teacher_id' => $ctx['teacher2']->id,
            'start_date' => '2026-03-16',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.timetable', [
                'start_date' => '2026-03-16',
                'end_date' => '2026-03-22',
                'teacher_id' => $ctx['teacher1']->id,
            ]));

        $response->assertOk();
        $response->assertSee('Alice Class');
        $response->assertDontSee('Bob Class');
    }

    /** @test */
    public function calendar_index_loads_with_view_options(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.index'));

        $response->assertOk();
        $response->assertSee('Academic Calendar');
        $response->assertSee('Add Event');
    }

    /** @test */
    public function calendar_index_can_filter_by_event_type(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Exam Filter Test',
            'event_type' => 'exam',
            'start_date' => '2026-06-01',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->get(route('calendar.index', ['event_type' => 'exam', 'date' => '2026-06-01', 'view' => 'month']));

        $response->assertOk();
    }

    /** @test */
    public function all_day_events_render_without_time(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Sports Day',
            'event_type' => 'academic_event',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'is_all_day' => true,
            'start_time' => null,
            'end_time' => null,
        ]));

        $event = CalendarEvent::where('title', 'Sports Day')->first();
        $this->assertTrue($event->is_all_day);
        $this->assertNull($event->start_time);
    }

    /** @test */
    public function conflict_detection_excludes_self_on_update(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Existing Event',
            'teacher_id' => $ctx['teacher1']->id,
            'room_id' => $ctx['room']->id,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]));

        $event = CalendarEvent::where('title', 'Existing Event')->first();

        // Update same event — should not conflict with itself
        $response = $this->actingAs($ctx['manager'])
            ->put(route('calendar.events.update', $event), [
                'title' => 'Existing Event Updated',
                'event_type' => 'class',
                'start_date' => $event->start_date->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'title' => 'Existing Event Updated']);
    }

    /** @test */
    public function cannot_create_event_without_required_fields(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $response = $this->actingAs($ctx['manager'])
            ->post(route('calendar.events.store'), []);

        $response->assertSessionHasErrors(['title', 'event_type', 'start_date']);
    }

    /** @test */
    public function manager_can_cancel_event(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'To Cancel',
        ]));

        $event = CalendarEvent::where('title', 'To Cancel')->first();

        $response = $this->actingAs($ctx['manager'])
            ->put(route('calendar.events.update', $event), [
                'title' => 'To Cancel',
                'event_type' => 'class',
                'status' => 'cancelled',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'status' => 'cancelled']);
    }

    /** @test */
    public function different_tenant_cannot_access_events(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Institute 1 Event',
        ]));

        $event = CalendarEvent::where('title', 'Institute 1 Event')->first();

        TenantContext::set($ctx['institute2']->id);

        $this->actingAs($ctx['foreignManager'])
            ->get(route('calendar.events.show', $event))
            ->assertNotFound();
    }

    /** @test */
    public function all_event_types_are_valid(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        foreach (['class', 'exam', 'practical', 'viva', 'assignment', 'holiday', 'training', 'meeting', 'academic_event', 'submission_deadline', 'result_publication', 'certificate_event'] as $type) {
            $response = $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
                'title' => "Event $type",
                'event_type' => $type,
                'start_date' => '2026-07-01',
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]));

            $response->assertRedirect();
            $this->assertDatabaseHas('calendar_events', ['title' => "Event $type", 'event_type' => $type]);
        }
    }

    /** @test */
    public function reminder_can_be_added_to_event(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Reminder Test',
        ]));

        $event = CalendarEvent::where('title', 'Reminder Test')->first();
        $service = app(CalendarEventService::class);
        $reminder = $service->addReminder($event, $ctx['manager']->id, 30, 'notification');

        $this->assertDatabaseHas('calendar_event_reminders', [
            'event_id' => $event->id,
            'user_id' => $ctx['manager']->id,
            'minutes_before' => 30,
            'reminder_type' => 'notification',
        ]);
    }

    /** @test */
    public function events_json_filters_by_branch(): void
    {
        $ctx = $this->seededContext();
        TenantContext::set($ctx['institute']->id);

        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Branch A Event',
            'branch_id' => $ctx['branch']->id,
            'start_date' => '2026-05-20',
        ]));
        $this->actingAs($ctx['manager'])->post(route('calendar.events.store'), $this->eventPayload([
            'title' => 'Branch B Event',
            'branch_id' => $ctx['branch2']->id,
            'start_date' => '2026-05-20',
        ]));

        $response = $this->actingAs($ctx['manager'])
            ->getJson(route('calendar.events.json', [
                'start' => '2026-05-01',
                'end' => '2026-05-31',
                'branch_id' => $ctx['branch']->id,
            ]));

        $data = $response->json();
        $titles = array_column($data, 'title');
        $this->assertContains('Branch A Event', $titles);
        $this->assertNotContains('Branch B Event', $titles);
    }
}
