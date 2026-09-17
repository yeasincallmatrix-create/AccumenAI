<?php

namespace App\Services;

use App\Support\InstituteDomain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent remediation backfill: institutes.industry_id / sub_industry_id
 * from the legacy string columns (institutes.industry / sub_industry).
 *
 * Why this exists: migration 2026_09_18_000004 ran before the taxonomy
 * seeder populated industries/sub_industries, so it matched nothing and
 * never reruns. This service is shared by the remediation migration and
 * the `taxonomy:backfill-institutes` artisan command.
 *
 * Safety contract:
 * - Only fills NULL FK columns; already-valid FK values are never touched.
 * - Legacy string columns are never modified.
 * - Exact canonical slug matching only (plus the app's own alias maps in
 *   InstituteDomain). No fuzzy matching, no guessing.
 * - Genuinely unmatched values are left NULL and reported.
 * - Ambiguous cross-country candidates are left NULL and reported
 *   (never "take first").
 * - Runs inside a transaction; FK checks stay enabled; nothing destructive.
 * - Safe to run more than once (second run is a no-op when all rows that
 *   can be resolved already are).
 */
final class InstituteTaxonomyBackfill
{
    /**
     * @return array{
     *   skipped: ?string,
     *   total: int,
     *   industry_filled: int,
     *   industry_already_set: int,
     *   industry_empty: int,
     *   sub_filled: int,
     *   sub_already_set: int,
     *   sub_empty: int,
     *   unmatched_industries: array<string>,
     *   unmatched_subs: array<string>,
     * }
     */
    public static function run(): array
    {
        $report = [
            'skipped' => null,
            'total' => 0,
            'industry_filled' => 0,
            'industry_already_set' => 0,
            'industry_empty' => 0,
            'sub_filled' => 0,
            'sub_already_set' => 0,
            'sub_empty' => 0,
            'unmatched_industries' => [],
            'unmatched_subs' => [],
        ];

        if (! Schema::hasTable('institutes')
            || ! Schema::hasTable('industries')
            || ! Schema::hasTable('sub_industries')) {
            $report['skipped'] = 'taxonomy tables unavailable';

            return $report;
        }

        $industries = DB::table('industries')->select('id', 'slug')->get();
        if ($industries->isEmpty()) {
            // Fresh install before seeding, or seeder not yet run:
            // nothing to resolve against — a later run (or the artisan
            // command after seeding) will pick the rows up.
            $report['skipped'] = 'taxonomy not seeded';

            return $report;
        }

        $industryBySlug = $industries->keyBy('slug');

        DB::transaction(function () use (&$report, $industryBySlug) {
            $report['total'] = (int) DB::table('institutes')->count();

            // Pass 1 — industry_id from legacy institutes.industry.
            DB::table('institutes')->orderBy('id')->chunkById(500, function ($rows) use (&$report, $industryBySlug) {
                foreach ($rows as $inst) {
                    if ($inst->industry_id !== null) {
                        $report['industry_already_set']++;

                        continue;
                    }

                    $raw = trim((string) ($inst->industry ?? ''));
                    if ($raw === '') {
                        $report['industry_empty']++;

                        continue;
                    }

                    $industry = $industryBySlug->get($raw)
                        ?? $industryBySlug->get(InstituteDomain::normalizeIndustry(strtolower($raw)));

                    if ($industry === null) {
                        self::recordUnmatched($report, 'unmatched_industries', $raw);

                        continue;
                    }

                    DB::table('institutes')->where('id', $inst->id)->update(['industry_id' => $industry->id]);
                    $report['industry_filled']++;
                }
            });

            // Pass 2 — sub_industry_id within the resolved industry scope.
            DB::table('institutes')->orderBy('id')->chunkById(500, function ($rows) use (&$report) {
                foreach ($rows as $inst) {
                    if ($inst->sub_industry_id !== null) {
                        $report['sub_already_set']++;

                        continue;
                    }

                    $rawSub = trim((string) ($inst->sub_industry ?? ''));
                    if ($rawSub === '') {
                        $report['sub_empty']++;

                        continue;
                    }

                    if ($inst->industry_id === null) {
                        // Parent industry unknown — resolving the sub would
                        // violate the parent-consistency invariant.
                        self::recordUnmatched($report, 'unmatched_subs', '(no industry)/' . $rawSub);

                        continue;
                    }

                    $slugs = [$rawSub];
                    $normalized = InstituteDomain::normalizeSubIndustry('', strtolower($rawSub));
                    if ($normalized !== $rawSub && $normalized !== '') {
                        $slugs[] = $normalized;
                    }

                    $candidates = DB::table('sub_industries')
                        ->where('industry_id', $inst->industry_id)
                        ->whereIn('slug', $slugs)
                        ->get()
                        // Prefer the exact legacy slug over the normalized alias.
                        ->sortBy(fn ($c) => array_search($c->slug, $slugs, true))
                        ->values();

                    if ($candidates->isEmpty()) {
                        self::recordUnmatched($report, 'unmatched_subs', $inst->industry . '/' . $rawSub);

                        continue;
                    }

                    // Priority 1: exact country-specific match.
                    $match = ($inst->country_id !== null)
                        ? $candidates->firstWhere('country_id', (int) $inst->country_id)
                        : null;

                    // Priority 2: global row.
                    if ($match === null) {
                        $match = $candidates->firstWhere('country_id', null);
                    }

                    if ($match === null) {
                        // Ambiguous (exists only under other countries) —
                        // leave NULL and report instead of guessing.
                        self::recordUnmatched($report, 'unmatched_subs', $inst->industry . '/' . $rawSub . ' (country scope)');

                        continue;
                    }

                    DB::table('institutes')->where('id', $inst->id)->update(['sub_industry_id' => $match->id]);
                    $report['sub_filled']++;
                }
            });
        });

        $report['unmatched_industries'] = array_values(array_unique($report['unmatched_industries']));
        $report['unmatched_subs'] = array_values(array_unique($report['unmatched_subs']));
        sort($report['unmatched_industries']);
        sort($report['unmatched_subs']);

        return $report;
    }

    private static function recordUnmatched(array &$report, string $key, string $value): void
    {
        if (! in_array($value, $report[$key], true)) {
            $report[$key][] = $value;
        }
    }
}
