<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\NumberSequenceService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 04 — Identifier & numbering hardening.
 *
 * Covers: format contract per type, sequential allocation, per-tenant
 * isolation, year rollover, rapid-allocation uniqueness, retired-gap
 * behavior, legacy-row preservation, non-consuming preview, and the
 * cross-tenant negative. True OS-thread parallelism is unavailable in
 * PHPUnit; concurrency is proven via serialized row-locked allocation under
 * rapid sequential load plus the unique DB key as backstop (documented).
 */
class NumberSequenceTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $a;

    private Institute $b;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->a = Institute::create([
            'name' => 'Sequence Hospital A',
            'slug' => 'sequence-a-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $this->b = Institute::create([
            'name' => 'Sequence Hospital B',
            'slug' => 'sequence-b-'.uniqid(),
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
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->a->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->a->id);
    }

    private function sequences(): NumberSequenceService
    {
        return app(NumberSequenceService::class);
    }

    private function year(): string
    {
        return now()->format('Y');
    }

    // Format contract per type (stored shape: PREFIX-YYYY-NNNNN, no
    // tenant segment — uniqueness is per tenant via composite DB keys).
    public function test_format_contract_per_type(): void
    {
        $y = $this->year();

        $this->assertSame("MR-{$y}-00001", $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id));
        $this->assertSame("RX-{$y}-00001", $this->sequences()->next(NumberSequence::TYPE_PRESCRIPTION, $this->a->id));
        $this->assertSame("LAB-{$y}-00001", $this->sequences()->next(NumberSequence::TYPE_LAB_ORDER, $this->a->id));
        $this->assertSame("INV-{$y}-00001", $this->sequences()->next(NumberSequence::TYPE_INVOICE, $this->a->id));
        $this->assertSame("TPA-{$y}-00001", $this->sequences()->next(NumberSequence::TYPE_TPA_CLAIM, $this->a->id));
    }

    // Sequential allocation within a series.
    public function test_sequential_allocation(): void
    {
        $first = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $second = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);

        $this->assertSame((int) substr($first, -5) + 1, (int) substr($second, -5));
        $this->assertSame(2, (int) NumberSequence::where('institute_id', $this->a->id)
            ->where('sequence_type', NumberSequence::TYPE_MR)->firstOrFail()->last_number);
    }

    // Tenant isolation: independent counters; the same stored number may
    // exist in both tenants (composite per-tenant uniqueness) — the
    // tenant scope, not a number segment, keeps them apart.
    public function test_sequences_isolated_per_institute(): void
    {
        $a1 = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $a2 = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $b1 = $this->sequences()->next(NumberSequence::TYPE_MR, $this->b->id);

        $this->assertSame($a1, $b1);
        $this->assertSame(substr($a1, -5), substr($b1, -5));

        // B's allocation never touches A's counter row.
        $this->assertSame(2, (int) NumberSequence::where('institute_id', $this->a->id)
            ->where('sequence_type', NumberSequence::TYPE_MR)->firstOrFail()->last_number);
        $this->assertSame(1, (int) NumberSequence::where('institute_id', $this->b->id)
            ->where('sequence_type', NumberSequence::TYPE_MR)->firstOrFail()->last_number);
    }

    // Display/search contract: stored YYYY, human-facing YY, legacy
    // tenant form accepted everywhere.
    public function test_display_shortens_year_and_normalizers_round_trip(): void
    {
        $y = $this->year();
        $yy = substr($y, 2);

        $stored = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $this->assertSame("MR-{$y}-00001", $stored);
        $this->assertSame("MR-{$yy}-00001", NumberSequenceService::display($stored));
        $this->assertSame($stored, NumberSequenceService::toStored("MR-{$yy}-00001"));

        $legacy = "MR-{$y}-189-00002";
        $this->assertSame("MR-{$y}-00002", NumberSequenceService::toStored($legacy));
        $this->assertSame("MR-{$yy}-00002", NumberSequenceService::display($legacy));

        $this->assertSame($stored, NumberSequenceService::expandShortYears("MR-{$yy}-00001"));
        // Four-digit years are never rewritten.
        $this->assertSame($stored, NumberSequenceService::expandShortYears($stored));
    }

    // Same stored number may live in two tenants (per-tenant uniqueness).
    public function test_same_number_reusable_across_tenants(): void
    {
        $mr = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);

        $make = fn (Institute $institute, string $phone) => Patient::create([
            'institute_id' => $institute->id,
            'mr_number' => $mr,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => $phone,
        ]);

        $make($this->a, '0100000011');
        $make($this->b, '0100000012');

        $this->assertSame(1, Patient::where('institute_id', $this->a->id)->where('mr_number', $mr)->count());
        $this->assertSame(1, Patient::where('institute_id', $this->b->id)->where('mr_number', $mr)->count());
    }

    // Year rollover starts a fresh series.
    public function test_year_rollover_starts_fresh_series(): void
    {
        $this->sequences()->next(NumberSequence::TYPE_INVOICE, $this->a->id);

        try {
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::create((int) $this->year() + 1, 6, 1));
            $rolled = $this->sequences()->next(NumberSequence::TYPE_INVOICE, $this->a->id);
        } finally {
            \Carbon\Carbon::setTestNow();
        }

        $this->assertStringEndsWith('-00001', $rolled);
        $this->assertStringContainsString('-'.((int) $this->year() + 1).'-', $rolled);
    }

    // Rapid allocation: unique and strictly increasing (serialized locking).
    public function test_rapid_allocation_unique_and_increasing(): void
    {
        $numbers = [];
        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $this->sequences()->next(NumberSequence::TYPE_PRESCRIPTION, $this->a->id);
        }

        $this->assertSame(25, count(array_unique($numbers)));
        $suffixes = array_map(fn ($n) => (int) substr($n, -5), $numbers);
        $sorted = $suffixes;
        sort($sorted);
        $this->assertSame($sorted, $suffixes);
    }

    // Retired numbers are never reused (explicit gap behavior).
    public function test_retired_numbers_never_reused(): void
    {
        $patient = Patient::create([
            'institute_id' => $this->a->id,
            'mr_number' => 'MR-TEST-1',
            'first_name' => 'Gap',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '0100000001',
        ]);
        $rx1 = Prescription::create([
            'institute_id' => $this->a->id,
            'patient_id' => $patient->id,
            'prescription_number' => $this->sequences()->next(NumberSequence::TYPE_PRESCRIPTION, $this->a->id),
            'prescription_date' => now()->format('Y-m-d'),
        ]);
        $first = $rx1->prescription_number;
        $rx1->items()->delete();
        $rx1->delete();

        $second = $this->sequences()->next(NumberSequence::TYPE_PRESCRIPTION, $this->a->id);
        $this->assertSame((int) substr($first, -5) + 1, (int) substr($second, -5));
        $this->assertSame(0, Prescription::where('prescription_number', $first)->count());
    }

    // Legacy identifiers are preserved and never collide.
    public function test_legacy_identifiers_preserved(): void
    {
        $legacy = Patient::create([
            'institute_id' => $this->a->id,
            'mr_number' => '26474',
            'first_name' => 'Legacy',
            'last_name' => 'Row',
            'date_of_birth' => '1980-01-01',
            'gender' => 'female',
            'phone' => '0100000002',
        ]);

        $fresh = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);

        $this->assertMatchesRegularExpression('/^MR-\d{4}-\d{5}$/', $fresh);
        $this->assertNotSame('26474', $fresh);
        $this->assertSame('26474', $legacy->fresh()->mr_number);
    }

    // Preview never consumes the sequence.
    public function test_peek_does_not_consume(): void
    {
        $peek1 = $this->sequences()->peek(NumberSequence::TYPE_MR, $this->a->id);
        $peek2 = $this->sequences()->peek(NumberSequence::TYPE_MR, $this->a->id);
        $this->assertSame($peek1, $peek2);

        $allocated = $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $this->assertSame($peek1, $allocated);
        $this->assertSame(0, NumberSequence::where('institute_id', $this->b->id)->count());
    }

    // Cross-tenant negative: no shared or observable counter state.
    public function test_cross_tenant_counter_state_isolated(): void
    {
        $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);
        $this->sequences()->next(NumberSequence::TYPE_MR, $this->a->id);

        $this->assertSame(0, NumberSequence::where('institute_id', $this->b->id)->count());
        $bFirst = $this->sequences()->next(NumberSequence::TYPE_MR, $this->b->id);
        $this->assertStringEndsWith('-00001', $bFirst);
    }
}
