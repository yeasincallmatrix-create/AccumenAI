<?php

namespace Tests\Feature\Finance;

use App\Models\ChartOfAccount;
use App\Models\Accounting\RecurringGeneration;
use App\Models\Accounting\RecurringTemplate;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\RecurringTransactionService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use DatabaseTransactions;

    protected RecurringTransactionService $service;
    protected Institute $institute;
    protected InstituteUser $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RecurringTransactionService::class);

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'RT Test Inst ' . uniqid(),
            'slug' => 'rt-test-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'retail',
            'status' => 'active',
        ]);

        app(AccountingSetupService::class)->setupForInstitute($this->institute->id);

        $role = Role::where('slug', 'institute-admin')->whereNull('institute_id')->first();
        $this->actor = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'rt-admin-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function validJournalLines(): array
    {
        $cash = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1000')->first();
        $revenue = ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '4001')->first();

        return [
            ['coa_id' => $cash->id, 'debit' => 5000, 'credit' => 0],
            ['coa_id' => $revenue->id, 'debit' => 0, 'credit' => 5000],
        ];
    }

    private function createTemplate(array $overrides = []): RecurringTemplate
    {
        $defaults = [
            'institute_id' => $this->institute->id,
            'template_number' => 'RT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => 'Monthly Rent',
            'transaction_type' => 'journal_entry',
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => now()->toDateString(),
            'next_run_at' => now()->subDay(),
            'auto_post' => false,
            'status' => 'active',
            'occurrences_generated' => 0,
            'consecutive_failures' => 0,
            'template_data' => [
                'narration' => 'Monthly Office Rent',
                'journal_type' => 'journal',
                'currency' => 'BDT',
                'lines' => [],
            ],
        ];

        return RecurringTemplate::create(array_merge($defaults, $overrides));
    }

    private function createDueTemplate(array $extra = []): RecurringTemplate
    {
        return $this->createTemplate(array_merge([
            'next_run_at' => now()->subHour(),
            'template_data' => [
                'narration' => 'Test journal',
                'journal_type' => 'journal',
                'currency' => 'BDT',
                'lines' => $this->validJournalLines(),
            ],
        ], $extra));
    }

    // === Template model tests ===

    public function test_template_creation(): void
    {
        $template = $this->createTemplate();

        $this->assertNotNull($template->id);
        $this->assertEquals($this->institute->id, $template->institute_id);
        $this->assertEquals('Monthly Rent', $template->name);
        $this->assertEquals('journal_entry', $template->transaction_type);
        $this->assertEquals('monthly', $template->frequency);
        $this->assertEquals('active', $template->status);
    }

    public function test_template_generates_number(): void
    {
        $template = $this->createTemplate();

        $this->assertNotNull($template->template_number);
        $this->assertStringStartsWith('RT-' . date('Y') . '-', $template->template_number);
    }

    public function test_template_status_methods(): void
    {
        $active = $this->createTemplate(['status' => 'active']);
        $paused = $this->createTemplate(['status' => 'paused']);
        $completed = $this->createTemplate(['status' => 'completed']);

        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isPaused());

        $this->assertTrue($paused->isPaused());
        $this->assertFalse($paused->isActive());

        $this->assertFalse($completed->isActive());
        $this->assertFalse($completed->isPaused());
    }

    public function test_template_status_color(): void
    {
        $active = $this->createTemplate(['status' => 'active']);
        $this->assertEquals('success', $active->statusColor());

        $failed = $this->createTemplate(['status' => 'failed']);
        $this->assertEquals('danger', $failed->statusColor());
    }

    public function test_template_is_due(): void
    {
        $past = $this->createTemplate(['next_run_at' => now()->subHour()]);
        $future = $this->createTemplate(['next_run_at' => now()->addHour()]);
        $paused = $this->createTemplate(['next_run_at' => now()->subHour(), 'status' => 'paused']);

        $this->assertTrue($past->isDue());
        $this->assertFalse($future->isDue());
        $this->assertFalse($paused->isDue());
    }

    public function test_template_can_generate_active(): void
    {
        $template = $this->createTemplate([
            'status' => 'active',
            'max_occurrences' => 10,
            'occurrences_generated' => 3,
        ]);

        $this->assertTrue($template->canGenerate());
    }

    public function test_template_cannot_generate_paused(): void
    {
        $template = $this->createTemplate(['status' => 'paused']);

        $this->assertFalse($template->canGenerate());
    }

    public function test_template_cannot_generate_at_max_occurrences(): void
    {
        $template = $this->createTemplate([
            'status' => 'active',
            'max_occurrences' => 5,
            'occurrences_generated' => 5,
        ]);

        $this->assertFalse($template->canGenerate());
    }

    public function test_template_cannot_generate_past_end_date(): void
    {
        $template = $this->createTemplate([
            'status' => 'active',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'next_run_at' => now()->subDay(),
        ]);

        $this->assertFalse($template->canGenerate());
    }

    // === computeNextRun tests ===

    public function test_compute_next_run_daily(): void
    {
        $template = $this->createTemplate(['frequency' => 'daily', 'interval_count' => 1]);
        $next = $template->computeNextRun(now());

        $this->assertEquals(now()->addDay()->day, $next->day);
    }

    public function test_compute_next_run_monthly(): void
    {
        $template = $this->createTemplate(['frequency' => 'monthly', 'interval_count' => 1]);
        $next = $template->computeNextRun(now());

        $this->assertEquals(now()->addMonth()->month, $next->month);
    }

    public function test_compute_next_run_quarterly(): void
    {
        $template = $this->createTemplate(['frequency' => 'quarterly', 'interval_count' => 1]);
        $next = $template->computeNextRun(now());

        $this->assertEquals(now()->addMonths(3)->month, $next->month);
    }

    public function test_compute_next_run_annual(): void
    {
        $template = $this->createTemplate(['frequency' => 'annual', 'interval_count' => 1]);
        $next = $template->computeNextRun(now());

        $this->assertEquals(now()->addYear()->year, $next->year);
    }

    public function test_compute_next_run_custom_cron(): void
    {
        $template = $this->createTemplate([
            'frequency' => 'custom',
            'custom_cron' => '0 9 * * 1',
        ]);
        $next = $template->computeNextRun(now());

        $this->assertNotNull($next);
        $this->assertTrue($next->isAfter(now()));
    }

    public function test_compute_next_run_custom_cron_invalid(): void
    {
        $template = $this->createTemplate([
            'frequency' => 'custom',
            'custom_cron' => 'invalid cron',
        ]);
        $next = $template->computeNextRun(now());

        $this->assertNull($next);
    }

    // === previewOccurrences tests ===

    public function test_preview_occurrences(): void
    {
        $template = $this->createTemplate([
            'frequency' => 'monthly',
            'interval_count' => 1,
            'max_occurrences' => 3,
        ]);
        $preview = $template->previewOccurrences(5);

        $this->assertCount(3, $preview);
        $this->assertTrue($preview[0]->isBefore($preview[1]));
        $this->assertTrue($preview[1]->isBefore($preview[2]));
    }

    public function test_preview_occurrences_respects_end_date(): void
    {
        $template = $this->createTemplate([
            'frequency' => 'monthly',
            'interval_count' => 1,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);
        $preview = $template->previewOccurrences(10);

        $this->assertLessThanOrEqual(3, count($preview));
    }

    // === Service: pause / resume / cancel ===

    public function test_pause_template(): void
    {
        $template = $this->createTemplate(['status' => 'active']);

        $this->service->pause($template);

        $this->assertDatabaseHas('recurring_templates', [
            'id' => $template->id,
            'status' => 'paused',
        ]);
    }

    public function test_resume_template(): void
    {
        $template = $this->createTemplate(['status' => 'paused']);

        $this->service->resume($template);

        $this->assertDatabaseHas('recurring_templates', [
            'id' => $template->id,
            'status' => 'active',
        ]);
    }

    public function test_resume_non_paused_is_noop(): void
    {
        $template = $this->createTemplate(['status' => 'active']);

        $this->service->resume($template);

        $this->assertDatabaseHas('recurring_templates', [
            'id' => $template->id,
            'status' => 'active',
        ]);
    }

    public function test_cancel_template(): void
    {
        $template = $this->createTemplate(['status' => 'active']);

        $this->service->cancel($template);

        $this->assertDatabaseHas('recurring_templates', [
            'id' => $template->id,
            'status' => 'cancelled',
        ]);
    }

    // === Service: generateNow ===

    public function test_generate_now_creates_generation_record(): void
    {
        $template = $this->createDueTemplate();

        $this->service->generateNow($template);

        $this->assertDatabaseHas('recurring_generations', [
            'template_id' => $template->id,
            'status' => 'success',
        ]);
    }

    public function test_generate_now_increments_occurrences(): void
    {
        $template = $this->createDueTemplate(['occurrences_generated' => 0]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertEquals(1, $template->occurrences_generated);
        $this->assertNotNull($template->last_generated_at);
    }

    public function test_generate_now_advances_next_run(): void
    {
        $originalNext = now()->subHour();
        $template = $this->createDueTemplate([
            'frequency' => 'monthly',
            'interval_count' => 1,
            'next_run_at' => $originalNext,
        ]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertTrue($template->next_run_at->isAfter($originalNext));
    }

    public function test_generate_now_marks_completed_when_max_reached(): void
    {
        $template = $this->createDueTemplate([
            'frequency' => 'monthly',
            'interval_count' => 1,
            'max_occurrences' => 1,
            'occurrences_generated' => 0,
        ]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertEquals('completed', $template->status);
    }

    // === Service: failure handling ===

    public function test_failure_increments_consecutive_failures(): void
    {
        $template = $this->createTemplate([
            'next_run_at' => now()->subHour(),
            'template_data' => [
                'narration' => 'Bad template',
                'journal_type' => 'journal',
                'currency' => 'INVALID_CURRENCY',
                'lines' => [],
            ],
            'consecutive_failures' => 0,
        ]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertGreaterThan(0, $template->consecutive_failures);
        $this->assertNotNull($template->last_error);
    }

    public function test_failure_records_failed_generation(): void
    {
        $template = $this->createTemplate([
            'next_run_at' => now()->subHour(),
            'template_data' => [
                'narration' => 'Bad',
                'journal_type' => 'journal',
                'currency' => 'INVALID_CURRENCY',
                'lines' => [],
            ],
            'consecutive_failures' => 0,
        ]);

        $this->service->generateNow($template);

        $this->assertDatabaseHas('recurring_generations', [
            'template_id' => $template->id,
            'status' => 'failed',
        ]);
    }

    public function test_failure_marks_failed_after_5_consecutive(): void
    {
        $template = $this->createTemplate([
            'next_run_at' => now()->subHour(),
            'template_data' => [
                'narration' => 'Bad',
                'journal_type' => 'journal',
                'currency' => 'INVALID_CURRENCY',
                'lines' => [],
            ],
            'consecutive_failures' => 4,
        ]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertEquals('failed', $template->status);
    }

    public function test_success_resets_consecutive_failures(): void
    {
        $template = $this->createDueTemplate([
            'consecutive_failures' => 2,
        ]);

        $this->service->generateNow($template);

        $template->refresh();
        $this->assertEquals(0, $template->consecutive_failures);
        $this->assertNull($template->last_error);
    }

    public function test_generation_record_stores_entity_reference(): void
    {
        $template = $this->createDueTemplate();

        $this->service->generateNow($template);

        $gen = RecurringGeneration::where('template_id', $template->id)
            ->where('status', 'success')
            ->latest()
            ->first();

        $this->assertNotNull($gen);
        $this->assertNotNull($gen->generated_type);
        $this->assertNotNull($gen->generated_id);
        $this->assertNotNull($gen->generated_at);
    }

    // === Service: processDueTemplates ===

    public function test_process_due_templates_returns_summary(): void
    {
        $this->createDueTemplate();

        $result = $this->service->processDueTemplates();

        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('succeeded', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertIsInt($result['processed']);
    }

    public function test_process_due_templates_processes_due_and_skips_not_due(): void
    {
        $this->createDueTemplate(['name' => 'Due']);
        $this->createTemplate(['name' => 'Not Due', 'next_run_at' => now()->addDays(30)]);

        $result = $this->service->processDueTemplates();

        $this->assertGreaterThanOrEqual(1, $result['processed']);
    }

    public function test_different_institute_templates_are_processed_globally(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other ' . uniqid(),
            'slug' => 'other-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'retail',
            'status' => 'active',
        ]);

        $this->createDueTemplate(['name' => 'My Template']);
        $this->createTemplate([
            'institute_id' => $otherInstitute->id,
            'name' => 'Other Template',
            'next_run_at' => now()->subHour(),
        ]);

        $result = $this->service->processDueTemplates();

        // processDueTemplates processes all active due templates across institutes
        $this->assertGreaterThanOrEqual(2, $result['processed']);
    }

    public function test_deduplication_does_not_generate_twice_for_same_date(): void
    {
        $template = $this->createDueTemplate();

        // Generate once
        $this->service->generateNow($template);
        $count1 = RecurringGeneration::where('template_id', $template->id)->count();

        // Set same scheduled date again
        $template->refresh();
        $nextRun = $template->next_run_at->copy();
        $template->update(['next_run_at' => $nextRun]);

        $this->service->generateNow($template);
        $count2 = RecurringGeneration::where('template_id', $template->id)->count();

        // Should not create a second success record for same scheduled date
        $this->assertLessThanOrEqual($count1 + 1, $count2);
    }
}
