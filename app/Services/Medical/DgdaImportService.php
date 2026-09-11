<?php

namespace App\Services\Medical;

use App\Models\Medical\DgdaImportBatch;
use App\Models\Medical\DgdaRegistration;
use App\Models\Medical\MedicineConcept;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineProduct;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11 — operator-controlled DGDA regulatory import.
 *
 * Source contract (DGDA "Registered Drugs" published columns): DAR number
 * (stable registry identity), brand, generic, strength, dosage form,
 * manufacturer (+importer/validity/status where the export carries them).
 * There is no verified public DGDA API or bulk feed; ingestion consumes an
 * officially obtained export file provided by the operator — never scraping,
 * never fabricated identifiers.
 *
 * Pipeline per row, each in its own transaction (batch-level failures can
 * never leave a half-written product/identifier/registration triple):
 *   validate → normalize → match (L1 exact identifier / L2 deterministic
 *   tuple / create / ambiguous) → reconcile → persist + batch counters.
 *
 * Deliberately never touches: medicines, institute_medicines (codes/prices),
 * stock, dispenses, prescriptions, invoices. Tenant catalogs stay
 * tenant-controlled; regulatory rows are global and carry no tenant data.
 */
final class DgdaImportService
{
    /**
     * Header aliases (lowercased, trimmed) for the official export columns.
     */
    public const HEADER_ALIASES = [
        'dar_number' => ['dar#', 'dar_no', 'dar', 'dar number', 'dar_number', 'darno', 'registration_no', 'reg_no'],
        'brand_name' => ['brand', 'brand name', 'brand_name', 'trade_name', 'trade name', 'product_name', 'product name'],
        'generic_name' => ['generic', 'generic name', 'generic_name', 'ingredients', 'composition'],
        'strength_raw' => ['strength', 'strength_raw', 'power'],
        'dosage_form_raw' => ['dosage_form', 'dosage form', 'dosages_form', 'dosages form', 'form', 'dosageform', 'presentation'],
        'manufacturer_name' => ['manufacturer', 'manufacturer name', 'manufacturer_name', 'mfg', 'company'],
        'importer_name' => ['importer', 'importer name', 'importer_name'],
        'status_raw' => ['status', 'reg_status', 'registration_status', 'use_for', 'usefor', 'use for'],
        'valid_upto' => ['valid_up_to_date', 'valid upto date', 'valid_upto', 'valid upto', 'validity', 'valid_upto_date', 'expiry', 'valid_up_to'],
    ];

    public function __construct(private readonly MedicineTerminologyService $terminology) {}

    /**
     * Parse an official export CSV into [headers, rows]. Throws on unreadable
     * or empty files — a batch is never opened for those.
     *
     * @return array{headers: string[], rows: array<int, array{line: int, data: array}>}
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("Import file is not readable: {$path}");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lines === []) {
            throw new \RuntimeException('Import file is empty.');
        }
        // Strip UTF-8 BOM from the header line.
        $lines[0] = ltrim($lines[0], "\xEF\xBB\xBF");
        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv(array_shift($lines)));

        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = ['line' => $i + 2, 'data' => $this->mapRow($headers, str_getcsv($line))];
        }
        if ($rows === []) {
            throw new \RuntimeException('Import file contains no data rows.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Validate one mapped row. Only a missing DAR# rejects (it is the stable
     * source identity); everything else degrades to unmatched, never to a
     * guess.
     *
     * @return array{valid: bool, errors: string[], record: array}
     */
    public function validateRow(array $data, int $line): array
    {
        $record = [
            'dar_number' => trim((string) ($data['dar_number'] ?? '')),
            'brand_name' => $this->clean($data['brand_name'] ?? null, 190),
            'generic_name' => $this->clean($data['generic_name'] ?? null, 190),
            'strength_raw' => $this->clean($data['strength_raw'] ?? null, 100),
            'dosage_form_raw' => $this->clean($data['dosage_form_raw'] ?? null, 60),
            'manufacturer_name' => $this->clean($data['manufacturer_name'] ?? null, 190),
            'importer_name' => $this->clean($data['importer_name'] ?? null, 190),
            'status_raw' => $this->clean($data['status_raw'] ?? null, 60),
            'valid_upto' => $this->parseDate($data['valid_upto'] ?? null),
            'raw_payload' => $data,
        ];

        $errors = [];
        if ($record['dar_number'] === '') {
            $errors[] = "line {$line}: missing DAR number (stable source identity required)";
        }
        if (($record['generic_name'] ?? null) === null) {
            $errors[] = "line {$line}: missing generic name (row will stay unmatched)";
        }

        return ['valid' => $errors === [] || $record['dar_number'] !== '', 'errors' => $errors, 'record' => $record];
    }

    /**
     * Import one validated record. Returns the outcome category for batch
     * counters. Throws only on DB-level failures (transaction rolls back).
     */
    public function importRow(array $record, DgdaImportBatch $batch, array &$counters): string
    {
        // Callers (command, tests, future jobs) share this shape; missing
        // keys would raise under strict error handling — normalize first.
        $counters += ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'matched' => 0];

        return DB::transaction(function () use ($record, $batch, &$counters) {
            $existing = DgdaRegistration::where('dar_number', $record['dar_number'])->first();

            if ($existing) {
                return $this->reconcileExisting($existing, $record, $batch, $counters);
            }

            $registration = DgdaRegistration::create([
                'dgda_import_batch_id' => $batch->id,
                'dar_number' => $record['dar_number'],
                'brand_name' => $record['brand_name'],
                'generic_name' => $record['generic_name'],
                'strength_raw' => $record['strength_raw'],
                'dosage_form_raw' => $record['dosage_form_raw'],
                'manufacturer_name' => $record['manufacturer_name'],
                'importer_name' => $record['importer_name'],
                'status_raw' => $record['status_raw'],
                'valid_upto' => $record['valid_upto'],
                'match_status' => DgdaRegistration::MATCH_UNMATCHED,
                'raw_payload' => $record['raw_payload'],
                'retrieved_at' => now(),
            ]);
            $counters['created']++;

            return $this->matchRegistration($registration, $counters);
        });
    }

    /**
     * Match a registration to a global product:
     * L1 exact DAR identifier → L2 deterministic tuple → create (only when
     * no conflicting candidates) → ambiguous (candidates exist, form unclear).
     */
    public function matchRegistration(DgdaRegistration $registration, array &$counters): string
    {
        // L1 — the DAR# already identifies a product from an earlier import
        // or the Phase 10 legacy migration. Legacy migrated codes live under
        // type 'code', import-written ones under 'dar_number': same registry
        // value in either DGDA type is the same identifier.
        $identifier = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_DGDA)
            ->whereIn('identifier_type', ['dar_number', 'code'])
            ->where('value', $registration->dar_number)
            ->first();
        if ($identifier) {
            $registration->update([
                'medicine_product_id' => $identifier->medicine_product_id,
                'match_status' => DgdaRegistration::MATCH_EXACT,
                'match_detail' => 'DAR# matched existing regulatory identifier',
            ]);
            $counters['matched']++;

            return 'matched';
        }

        if ($registration->generic_name === null) {
            $registration->update([
                'match_status' => DgdaRegistration::MATCH_UNMATCHED,
                'match_detail' => 'No generic name — cannot determine a product',
            ]);
            $counters['unmatched']++;

            return 'unmatched';
        }

        // L2 — deterministic tuple (concept + strength + mapped form + brand).
        $form = $registration->dosage_form_raw
            ? \App\Models\Medical\MedicineForm::where('name', $registration->dosage_form_raw)->first()
            : null;
        $tuple = MedicineTerminologyService::normalizeName(
            MedicineTerminologyService::normalizeName($registration->generic_name)
            .'|'.($registration->strength_raw ?? '')
            .'|'.($form?->name ?? '')
            .'|'.($registration->brand_name ?? '')
        );
        $product = MedicineProduct::where('normalized_name', $tuple)->first();
        if ($product) {
            $this->linkProduct($registration, $product, DgdaRegistration::MATCH_DETERMINISTIC, 'Normalized tuple match');
            $counters['matched']++;

            return 'matched';
        }

        // Candidates exist but the form cannot pin exactly one product:
        // report ambiguity, create nothing, merge nothing.
        if ($form === null && $registration->dosage_form_raw) {
            $conceptNorm = MedicineTerminologyService::normalizeName($registration->generic_name);
            $candidates = MedicineProduct::whereHas('concept', fn ($q) => $q->where('normalized_name', $conceptNorm))
                ->when($registration->brand_name, fn ($q) => $q->where('brand_name', $registration->brand_name))
                ->pluck('id')
                ->all();
            if (count($candidates) > 1) {
                $registration->update([
                    'match_status' => DgdaRegistration::MATCH_AMBIGUOUS,
                    'match_detail' => 'Unmapped form "'.$registration->dosage_form_raw.'" with '.count($candidates)
                        .' same-concept/brand products: '.implode(',', array_slice($candidates, 0, 10)),
                ]);
                $counters['ambiguous']++;

                return 'ambiguous';
            }
        }

        // No conflict: materialize the product deterministically from source
        // strings (idempotent — same source always yields the same rows via
        // the unique normalized_name / identifier constraints).
        $product = $this->materializeProduct($registration, $form?->id);
        $this->linkProduct($registration, $product, DgdaRegistration::MATCH_DETERMINISTIC, 'Materialized from source record');
        $counters['matched']++;

        return 'matched';
    }

    private function reconcileExisting(DgdaRegistration $existing, array $record, DgdaImportBatch $batch, array &$counters): string
    {
        $dirty = false;
        foreach (['brand_name', 'generic_name', 'strength_raw', 'dosage_form_raw', 'manufacturer_name', 'importer_name', 'status_raw', 'valid_upto'] as $field) {
            if ($existing->{$field} != $record[$field]) {
                $existing->{$field} = $record[$field];
                $dirty = true;
            }
        }
        $existing->dgda_import_batch_id = $batch->id;
        $existing->retrieved_at = now();
        if ($dirty) {
            // Source fields mirror the registry; the product link never
            // jumps silently (re-match only unlinked rows).
            $existing->save();
            $counters['updated']++;

            return 'updated';
        }
        $existing->save();
        $counters['unchanged']++;

        return 'unchanged';
    }

    private function linkProduct(DgdaRegistration $registration, MedicineProduct $product, string $status, string $detail): void
    {
        $registration->update([
            'medicine_product_id' => $product->id,
            'match_status' => $status,
            'match_detail' => $detail,
        ]);
        MedicineIdentifier::firstOrCreate(
            [
                'system' => MedicineIdentifier::SYSTEM_DGDA,
                'identifier_type' => 'dar_number',
                'value' => $registration->dar_number,
            ],
            ['medicine_product_id' => $product->id, 'status' => 'active']
        );
    }

    private function materializeProduct(DgdaRegistration $registration, ?int $formId): MedicineProduct
    {
        $genericNames = MedicineTerminologyService::splitParts($registration->generic_name);
        $conceptName = implode(' + ', $genericNames);
        $concept = MedicineConcept::firstOrCreate(
            ['normalized_name' => MedicineTerminologyService::normalizeName($conceptName)],
            [
                'canonical_name' => $conceptName,
                'concept_type' => count($genericNames) > 1 ? 'combination' : 'single',
                'status' => 'active',
            ]
        );

        $strengthParts = MedicineTerminologyService::splitParts($registration->strength_raw);
        // Misaligned parts (e.g. one strength for two ingredients) are never
        // distributed by guessing — raw is preserved, structured stays null.
        $aligned = $strengthParts !== [] && count($strengthParts) === count($genericNames)
            ? array_map([MedicineTerminologyService::class, 'parseStrengthPart'], $strengthParts)
            : [];
        $display = trim(implode(' ', array_filter([
            $registration->generic_name.(($registration->brand_name ?? '') !== '' ? " ({$registration->brand_name})" : ''),
            $registration->strength_raw,
        ])));
        $normalized = MedicineTerminologyService::normalizeName(
            $concept->normalized_name.'|'.($registration->strength_raw ?? '').'|'
            .($formId ? (\App\Models\Medical\MedicineForm::find($formId)?->name ?? '') : '').'|'
            .($registration->brand_name ?? '')
        );

        $product = MedicineProduct::firstOrCreate(
            ['normalized_name' => $normalized],
            [
                'medicine_concept_id' => $concept->id,
                'medicine_form_id' => $formId,
                'display_name' => mb_substr($display !== '' ? $display : $concept->canonical_name, 0, 200),
                'strength_raw' => $registration->strength_raw,
                'brand_name' => $registration->brand_name,
                'status' => 'active',
            ]
        );

        foreach ($genericNames as $i => $name) {
            $ingredient = \App\Models\Medical\MedicineIngredient::firstOrCreate(
                ['normalized_name' => MedicineTerminologyService::normalizeName($name)],
                ['canonical_name' => $name, 'status' => 'active']
            );
            $strength = $aligned[$i] ?? [null, null];
            \App\Models\Medical\MedicineProductIngredient::firstOrCreate(
                [
                    'medicine_product_id' => $product->id,
                    'medicine_ingredient_id' => $ingredient->id,
                    'sequence' => $i,
                ],
                ['strength_value' => $strength[0], 'strength_unit' => $strength[1]]
            );
        }

        return $product;
    }

    private function mapRow(array $headers, array $values): array
    {
        $out = [];
        foreach ($headers as $i => $header) {
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($header, $aliases, true)) {
                    $out[$field] = $values[$i] ?? null;
                    break;
                }
            }
        }

        return $out;
    }

    private function clean(mixed $value, int $length): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    private function parseDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'] as $format) {
            try {
                $date = \Carbon\Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
