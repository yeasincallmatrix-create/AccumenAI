<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 123-G — N+1 Query Pattern Detection (READ-ONLY).
 *
 * Scans Eloquent models and controller patterns for missing eager loading.
 */
class N1DetectionService
{
    private const KNOWN_RELATIONSHIPS = [
        'students' => [
            'table' => 'students',
            'foreign_keys' => ['batch_id', 'institute_id', 'crm_contact_id', 'crm_lead_id', 'preferred_batch_id'],
            'known_n1_risks' => [
                'student->batch (batch_id without batch relation)',
                'student->institute (institute_id without institute relation)',
            ],
        ],
        'student_enrollments' => [
            'table' => 'student_enrollments',
            'foreign_keys' => ['student_id', 'batch_id', 'course_id', 'institute_id'],
            'known_n1_risks' => [
                'enrollment->student (student_id without student relation)',
                'enrollment->batch (batch_id without batch relation)',
                'enrollment->course (course_id without course relation)',
            ],
        ],
        'attendance' => [
            'table' => 'attendance',
            'foreign_keys' => ['student_id', 'batch_id', 'institute_id', 'marked_by'],
            'known_n1_risks' => [
                'attendance->student (student_id without student relation)',
            ],
        ],
        'journals' => [
            'table' => 'journals',
            'foreign_keys' => ['institute_id', 'branch_id', 'fiscal_year_id', 'currency_id'],
            'known_n1_risks' => [
                'journal->entries (no entries relation)',
            ],
        ],
        'journal_entries' => [
            'table' => 'journal_entries',
            'foreign_keys' => ['journal_id', 'coa_id', 'party_id', 'institute_id', 'branch_id', 'currency_id'],
            'known_n1_risks' => [
                'entry->journal (journal_id without journal relation)',
                'entry->coa (coa_id without coa relation)',
            ],
        ],
        'invoices' => [
            'table' => 'invoices',
            'foreign_keys' => ['student_id', 'institute_id'],
            'known_n1_risks' => [
                'invoice->student (student_id without student relation)',
                'invoice->items (no items relation)',
            ],
        ],
        'payments' => [
            'table' => 'payments',
            'foreign_keys' => ['institute_id', 'invoice_id'],
            'known_n1_risks' => [
                'payment->invoice (invoice_id without invoice relation)',
            ],
        ],
        'inventory_stock_levels' => [
            'table' => 'inventory_stock_levels',
            'foreign_keys' => ['institute_id', 'inventory_item_id', 'inventory_warehouse_id'],
            'known_n1_risks' => [
                'stock->item (inventory_item_id without item relation)',
                'stock->warehouse (inventory_warehouse_id without warehouse relation)',
            ],
        ],
    ];

    public function detect(): array
    {
        return $this->detectEnhanced()['findings'] ?? $this->detectLegacy();
    }

    private function detectLegacy(): array
    {
        $findings = [];

        foreach (self::KNOWN_RELATIONSHIPS as $model => $config) {
            if (! Schema::hasTable($config['table'])) continue;

            try {
                $count = (int) DB::table($config['table'])->count();
                $indexes = (new DatabaseIndexAuditService())->getIndexes($config['table']);
                $indexedCols = [];
                foreach ($indexes as $idx) {
                    foreach ($idx['columns'] as $col) {
                        $indexedCols[$col] = $idx['name'];
                    }
                }

                $fkWithoutIndex = [];
                foreach ($config['foreign_keys'] as $fk) {
                    if (! isset($indexedCols[$fk])) {
                        $fkWithoutIndex[] = $fk;
                    }
                }

                $findings[] = [
                    'model' => $model,
                    'table' => $config['table'],
                    'row_count' => $count,
                    'foreign_keys' => $config['foreign_keys'],
                    'fk_without_index' => $fkWithoutIndex,
                    'known_n1_risks' => $config['known_n1_risks'],
                    'eager_loading_advice' => $this->adviceForModel($model, $config),
                    'severity' => $count > 1000 ? 'HIGH' : ($count > 100 ? 'MEDIUM' : 'LOW'),
                ];
            } catch (\Throwable $e) {}
        }

        return $findings;
    }

    /**
     * Step 124-E — Enhanced N+1 with classification
     */
    public function detectEnhanced(): array
    {
        $findings = $this->detectLegacy();
        $enhanced = [];
        $fingerprints = [];
        try {
            $fingerprints = DB::table('query_fingerprints')->orderByDesc('execution_count')->limit(50)->get();
        } catch (\Throwable $e) {}

        foreach ($findings as $f) {
            $queryCount = $f['row_count'] * 2; // heuristic
            $hasRuntimePattern = false;
            foreach ($fingerprints as $fp) {
                if (str_contains(strtolower($fp->normalized_query ?? ''), strtolower($f['table']))) {
                    if ($fp->execution_count > 50) $hasRuntimePattern = true;
                }
            }

            $classification = 'REVIEW';
            $type = 'static code risk';
            if ($hasRuntimePattern && $f['row_count'] > 100) {
                $classification = 'CONFIRMED';
                $type = 'runtime query pattern';
            } elseif ($hasRuntimePattern) {
                $classification = 'SUSPECTED';
                $type = 'suspected N+1';
            } elseif (count($f['fk_without_index']) > 0) {
                $classification = 'REVIEW';
                $type = 'false positive';
            }

            $enhanced[] = array_merge($f, [
                'classification' => $classification,
                'type' => $type,
                'query_pattern' => $f['table'] . ' with ' . implode(',', $f['foreign_keys']),
                'query_count' => $queryCount,
                'affected_endpoint' => 'unknown (check logs)',
                'recommendation' => $classification === 'CONFIRMED' ? 'Add eager loading immediately' : 'Review with EXPLAIN',
                'severity' => $f['severity'],
            ]);
        }

        return [
            'findings' => $enhanced,
            'summary' => [
                'confirmed' => count(array_filter($enhanced, fn($x) => $x['classification'] === 'CONFIRMED')),
                'suspected' => count(array_filter($enhanced, fn($x) => $x['classification'] === 'SUSPECTED')),
                'review' => count(array_filter($enhanced, fn($x) => $x['classification'] === 'REVIEW')),
            ],
        ];
    }

    private function adviceForModel(string $model, array $config): array
    {
        $advice = [];
        foreach ($config['foreign_keys'] as $fk) {
            $related = str_replace('_id', '', $fk);
            $advice[] = "Use ->with('{$related}') when loading {$model} with {$fk} reference";
        }
        return $advice;
    }
}
