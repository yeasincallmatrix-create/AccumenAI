<?php

namespace App\Services\Medical;

use App\Models\Medical\MedicineForm;
use App\Models\Medical\MedicineIdentifier;
use App\Models\Medical\MedicineIngredient;
use App\Models\Medical\MedicineProduct;
use App\Models\Medical\RxNormConcept;
use App\Models\Medical\RxNormImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12 — operator-controlled RxNorm terminology import (NLM RxNav).
 *
 * There is no verified bulk feed wired by default; ingestion consumes an
 * operator-provided export shaped like RxNav concept records (RxCUI, name,
 * TTY, ingredient composition, strength, form, brand, status, release).
 * Live RxNav access exists only in RxNormClient (timeout-guarded, tested
 * faked) — the import path below never touches the network.
 *
 * Matching hierarchy: L1 existing RXCUI → L2 deterministic tuple →
 * L3 source-declared relationship agreement → L4 candidates (never merge)
 * → L5 unmapped. Retirements mark status, never delete. Tenant catalogs,
 * prices, stock, prescriptions and DGDA rows are never written here.
 */
final class RxNormImportService
{
    public const HEADER_ALIASES = [
        'rxcui' => ['rxcui', 'rx_cui', 'rxcui_code', 'concept_id', 'rxnorm_id'],
        'name' => ['name', 'concept_name', 'str', 'term'],
        'tty' => ['tty', 'term_type', 'termtype', 'concept_type'],
        'ingredient_names' => ['ingredients', 'ingredient_names', 'ingredient', 'in_names'],
        'ingredient_rxcuis' => ['ingredient_rxcuis', 'ingredient_rxcui', 'in_rxcuis'],
        'strength_value' => ['strength_value', 'strength', 'strengthvalue'],
        'strength_unit' => ['strength_unit', 'strengthunit', 'unit'],
        'dose_form' => ['dose_form', 'dose form', 'form', 'dosage_form', 'dosage form'],
        'brand_name' => ['brand_name', 'brand name', 'brand', 'bn'],
        'status' => ['status', 'concept_status'],
        'replaced_by_rxcui' => ['replaced_by', 'replaced_by_rxcui', 'replacement', 'reformulation'],
        'release' => ['release', 'release_version', 'version'],
    ];

    public const KNOWN_STATUSES = ['active', 'retired'];

    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("Import file is not readable: {$path}");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lines === []) {
            throw new \RuntimeException('Import file is empty.');
        }
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
     * @return array{valid: bool, errors: string[], record: array}
     */
    public function validateRow(array $data, int $line, ?string $defaultRelease = null): array
    {
        $rxcui = trim((string) ($data['rxcui'] ?? ''));
        $record = [
            'rxcui' => $rxcui,
            'name' => $this->clean($data['name'] ?? null, 255),
            'tty' => strtoupper(trim((string) ($data['tty'] ?? ''))),
            'ingredient_names' => $this->pipeList($data['ingredient_names'] ?? null),
            'ingredient_rxcuis' => $this->pipeList($data['ingredient_rxcuis'] ?? null),
            'strength_value' => $this->clean($data['strength_value'] ?? null, 40),
            'strength_unit' => $this->clean($data['strength_unit'] ?? null, 20),
            'dose_form' => $this->clean($data['dose_form'] ?? null, 60),
            'brand_name' => $this->clean($data['brand_name'] ?? null, 150),
            'status' => strtolower(trim((string) ($data['status'] ?? 'active'))),
            'replaced_by_rxcui' => trim((string) ($data['replaced_by_rxcui'] ?? '')) ?: null,
            'release' => $this->clean($data['release'] ?? $defaultRelease, 100),
            // Provenance keeps both the source strings and the parsed lists
            // downstream readers rely on (pipe-split arrays, source line).
            'raw_payload' => array_merge($data, [
                'source_line' => $line,
                'ingredient_names' => $this->pipeList($data['ingredient_names'] ?? null),
                'ingredient_rxcuis' => $this->pipeList($data['ingredient_rxcuis'] ?? null),
            ]),
        ];

        $errors = [];
        if ($rxcui === '' || ! ctype_digit($rxcui)) {
            $errors[] = "line {$line}: missing or non-numeric RXCUI (stable source identity required)";
        }
        if ($record['name'] === null) {
            $errors[] = "line {$line}: missing concept name";
        }
        if (! in_array($record['tty'], RxNormConcept::KNOWN_TTYS, true)) {
            $errors[] = "line {$line}: unknown TTY '{$record['tty']}'";
        }
        if (! in_array($record['status'], self::KNOWN_STATUSES, true)) {
            $errors[] = "line {$line}: unknown status '{$record['status']}'";
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'record' => $record];
    }

    /**
     * Import one validated record. Returns outcome for batch counters.
     */
    public function importRow(array $record, RxNormImportBatch $batch, array &$counters): string
    {
        $counters += ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'matched' => 0];

        return DB::transaction(function () use ($record, $batch, &$counters) {
            $existing = RxNormConcept::where('rxcui', $record['rxcui'])->first();
            if ($existing) {
                return $this->reconcileExisting($existing, $record, $batch, $counters);
            }

            $concept = RxNormConcept::create([
                'rxnorm_import_batch_id' => $batch->id,
                'rxcui' => $record['rxcui'],
                'name' => $record['name'],
                'tty' => $record['tty'],
                'source_release' => $record['release'],
                'status' => $record['status'] === 'retired' ? 'retired' : 'active',
                'replaced_by_rxcui' => $record['replaced_by_rxcui'],
                'match_status' => RxNormConcept::MATCH_UNMAPPED,
                'raw_payload' => $record['raw_payload'],
                'retrieved_at' => now(),
            ]);
            $counters['created']++;

            if ($concept->status === 'retired') {
                $this->retireIdentifier($concept, $counters);
                $concept->update([
                    'match_status' => RxNormConcept::MATCH_UNMAPPED,
                    'match_detail' => 'Retired concept: preserved, never linked',
                ]);

                return 'unmapped';
            }

            return $this->matchConcept($concept, $counters);
        });
    }

    /**
     * L1 exact → L2 deterministic → L3 relationship → L4 candidates → L5.
     */
    public function matchConcept(RxNormConcept $concept, array &$counters): string
    {
        // L1 — this RXCUI already identifies a target from an earlier import.
        $identifier = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_RXNORM)
            ->where('identifier_type', 'rxcui')
            ->where('value', $concept->rxcui)
            ->first();
        if ($identifier) {
            $this->attachIdentifierTarget($concept, $identifier, 'exact', 'RXCUI already attached');
            $counters['matched']++;

            return 'matched';
        }

        $tty = $concept->tty;

        // Ingredient-level concepts attach to ingredients only — never to
        // products (a shared salt is not a formulation).
        if (in_array($tty, RxNormConcept::INGREDIENT_TTYS, true)) {
            $ingredient = MedicineIngredient::where(
                'normalized_name',
                MedicineTerminologyService::normalizeName($concept->name)
            )->first();
            if (! $ingredient) {
                $ingredient = MedicineIngredient::create([
                    'canonical_name' => $concept->name,
                    'normalized_name' => MedicineTerminologyService::normalizeName($concept->name),
                    'status' => 'active',
                ]);
            }
            $this->attachIngredient($concept, $ingredient, 'deterministic', 'Exact normalized ingredient name');
            $counters['matched']++;

            return 'matched';
        }

        if (! in_array($tty, RxNormConcept::PRODUCT_TTYS, true)) {
            // BN/GPCK/BPCK/DF and friends: vocabulary-level rows, never
            // auto-linked to a formulation. Brand names may still yield
            // review candidates below.
            return $this->brandCandidatesOrUnmapped($concept, $counters);
        }

        // Product-level: resolve the full composition deterministically.
        $resolution = $this->resolveComposition($concept);
        if ($resolution['ambiguous']) {
            $concept->update([
                'match_status' => RxNormConcept::MATCH_AMBIGUOUS,
                'match_detail' => $resolution['reason'],
                'candidates' => $resolution['candidates'],
            ]);
            $counters['ambiguous']++;

            return 'ambiguous';
        }
        if ($resolution['product'] === null) {
            $concept->update([
                'match_status' => RxNormConcept::MATCH_UNMAPPED,
                'match_detail' => $resolution['reason'],
            ]);
            $counters['unmapped']++;

            return 'unmapped';
        }

        $method = $resolution['via_relationship'] ? 'relationship' : 'deterministic';
        $this->attachProduct($concept, $resolution['product'], $method, $resolution['reason']);
        $counters['matched']++;

        return 'matched';
    }

    /**
     * Resolve a product-level concept to exactly one local product or explain
     * why not. Returns ['product', 'ambiguous', 'reason', 'candidates',
     * 'via_relationship'].
     */
    private function resolveComposition(RxNormConcept $concept): array
    {
        $names = $concept->raw_payload['ingredient_names'] ?? [];
        if ($names === []) {
            // SCD-style display names carry no composition: fall back to the
            // generic-name split (single-ingredient rows only).
            $names = MedicineTerminologyService::splitParts($concept->name);
        }

        // L3 — source-declared ingredient RXCUIs resolve through existing
        // identifiers or exact normalized names (authoritative composition).
        $ingredientIds = [];
        $viaRelationship = false;
        $declared = $concept->raw_payload['ingredient_rxcuis'] ?? [];
        if ($declared !== []) {
            $viaRelationship = true;
            foreach ($declared as $rxcui) {
                $identifier = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_RXNORM)
                    ->where('identifier_type', 'rxcui')
                    ->where('value', (string) $rxcui)
                    ->first();
                $ingredientId = $identifier?->medicine_ingredient_id;
                if (! $ingredientId) {
                    return $this->unresolved('Declared ingredient RXCUI '.$rxcui.' has no local ingredient');
                }
                $ingredientIds[] = (int) $ingredientId;
            }
        } else {
            foreach ($names as $name) {
                $ingredient = MedicineIngredient::where(
                    'normalized_name',
                    MedicineTerminologyService::normalizeName($name)
                )->first();
                if (! $ingredient) {
                    return $this->unresolved("Ingredient '{$name}' has no local ingredient row");
                }
                $ingredientIds[] = (int) $ingredient->id;
            }
        }

        if ($ingredientIds === []) {
            return $this->unresolved('No ingredient composition determinable');
        }

        // Agreement: same ingredient SET, same strengths, same form,
        // same brand for branded (SBD/SBDC) concepts.
        $strength = $this->parseSourceStrength($concept);
        $form = $concept->raw_payload['dose_form'] ?? $concept->raw_payload['dose_form_raw'] ?? null;
        $formRow = $form ? MedicineForm::where('name', $form)->first() : null;
        if ($form && ! $formRow) {
            return $this->unresolved("Dose form '{$form}' is not in the controlled vocabulary");
        }

        $candidates = MedicineProduct::where('status', 'active')->get()->filter(
            function (MedicineProduct $product) use ($ingredientIds, $strength, $formRow, $concept) {
                $local = $product->productIngredients()->orderBy('sequence')->get();
                $localIds = $local->pluck('medicine_ingredient_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
                $want = collect($ingredientIds)->sort()->values()->all();
                if ($localIds !== $want) {
                    return false;
                }
                // Single-ingredient agreement includes strength; multi-
                // ingredient rows agree on the SET only (per-component
                // strengths stay raw — never distributed by guessing).
                if ($strength !== null && $local->count() === 1) {
                    $row = $local->first();
                    if (! $row || (float) $row->strength_value !== (float) $strength[0]
                        || strtolower((string) $row->strength_unit) !== strtolower((string) $strength[1])) {
                        return false;
                    }
                }
                if ($formRow && (int) $product->medicine_form_id !== (int) $formRow->id) {
                    return false;
                }
                if (in_array($concept->tty, ['SBD', 'SBDC'], true)) {
                    $brand = trim((string) ($concept->raw_payload['brand_name'] ?? ''));
                    if ($brand === '' || mb_strtolower($brand) !== mb_strtolower((string) ($product->brand_name ?? ''))) {
                        return false;
                    }
                }

                return true;
            }
        )->values();

        if ($candidates->count() === 1) {
            return [
                'product' => $candidates->first(), 'ambiguous' => false,
                'reason' => 'Ingredient set + strength + form'.($viaRelationship ? ' + declared relationships' : '').' agree',
                'candidates' => [], 'via_relationship' => $viaRelationship,
            ];
        }
        if ($candidates->count() > 1) {
            return [
                'product' => null, 'ambiguous' => true,
                'reason' => $candidates->count().' local products agree on composition; no auto-choice',
                'candidates' => $candidates->map(fn ($p) => [
                    'product_id' => $p->id, 'reason' => 'composition agreement',
                ])->all(),
                'via_relationship' => $viaRelationship,
            ];
        }

        return $this->unresolved('No local product agrees on composition');
    }

    private function unresolved(string $reason): array
    {
        return [
            'product' => null, 'ambiguous' => false, 'reason' => $reason,
            'candidates' => [], 'via_relationship' => false,
        ];
    }

    private function brandCandidatesOrUnmapped(RxNormConcept $concept, array &$counters): string
    {
        if ($concept->tty === 'BN' && trim($concept->name) !== '') {
            $norm = MedicineTerminologyService::normalizeName($concept->name);
            $candidates = MedicineProduct::whereRaw('LOWER(brand_name) = ?', [$norm])
                ->limit(11)
                ->get(['id', 'display_name']);
            if ($candidates->isNotEmpty()) {
                $concept->update([
                    'match_status' => RxNormConcept::MATCH_AMBIGUOUS,
                    'match_detail' => 'Brand concept: review candidates, never auto-linked',
                    'candidates' => $candidates->map(fn ($p) => [
                        'product_id' => $p->id, 'reason' => 'brand name match (review only)',
                    ])->all(),
                ]);
                $counters['ambiguous']++;

                return 'ambiguous';
            }
        }
        $concept->update([
            'match_status' => RxNormConcept::MATCH_UNMAPPED,
            'match_detail' => "TTY {$concept->tty} carries no product-level mapping",
        ]);
        $counters['unmapped']++;

        return 'unmapped';
    }

    private function attachProduct(RxNormConcept $concept, MedicineProduct $product, string $method, string $reason): void
    {
        $concept->update([
            'medicine_product_id' => $product->id,
            'medicine_ingredient_id' => null,
            'match_status' => $method === 'relationship' ? RxNormConcept::MATCH_RELATIONSHIP : RxNormConcept::MATCH_DETERMINISTIC,
            'match_detail' => $reason,
        ]);
        $this->attachIdentifier($concept, $product->id, null, $method, $reason);
    }

    private function attachIngredient(RxNormConcept $concept, MedicineIngredient $ingredient, string $method, string $reason): void
    {
        $concept->update([
            'medicine_ingredient_id' => $ingredient->id,
            'medicine_product_id' => null,
            'match_status' => $method === 'relationship' ? RxNormConcept::MATCH_RELATIONSHIP : RxNormConcept::MATCH_DETERMINISTIC,
            'match_detail' => $reason,
        ]);
        $this->attachIdentifier($concept, null, $ingredient->id, $method, $reason);
    }

    private function attachIdentifierTarget(RxNormConcept $concept, MedicineIdentifier $identifier, string $method, string $reason): void
    {
        $concept->update([
            'medicine_product_id' => $identifier->medicine_product_id,
            'medicine_ingredient_id' => $identifier->medicine_ingredient_id,
            'match_status' => $method === 'exact' ? RxNormConcept::MATCH_EXACT : RxNormConcept::MATCH_DETERMINISTIC,
            'match_detail' => $reason,
        ]);
        $meta = $identifier->metadata ?? [];
        $meta['mapping_history'][] = [
            'rxcui' => $concept->rxcui,
            'release' => $concept->source_release,
            'method' => $method,
            'at' => now()->toIso8601String(),
        ];
        $identifier->update(['metadata' => $meta]);
    }

    /**
     * Identifier attach with quarantine: the same (rxnorm, rxcui, value)
     * pointing at an unrelated target is reported, never re-pointed.
     */
    private function attachIdentifier(RxNormConcept $concept, ?int $productId, ?int $ingredientId, string $method, string $reason): void
    {
        $existing = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_RXNORM)
            ->where('identifier_type', 'rxcui')
            ->where('value', $concept->rxcui)
            ->first();

        if ($existing) {
            $sameTarget = (int) ($existing->medicine_product_id ?? 0) === (int) ($productId ?? 0)
                && (int) ($existing->medicine_ingredient_id ?? 0) === (int) ($ingredientId ?? 0);
            if (! $sameTarget) {
                $concept->update([
                    'match_status' => RxNormConcept::MATCH_AMBIGUOUS,
                    'match_detail' => 'RXCUI already attached elsewhere (identifier #'.$existing->id.') — quarantined',
                    'candidates' => [['identifier_id' => $existing->id, 'reason' => 'conflicting attachment']],
                ]);

                return;
            }
            $meta = $existing->metadata ?? [];
            $meta['mapping_history'][] = $this->provenance($concept, $method, $reason);
            $existing->update(['metadata' => $meta, 'status' => 'active']);

            return;
        }

        if (($productId === null) === ($ingredientId === null)) {
            throw new \RuntimeException('Identifier requires exactly one of product/ingredient.');
        }
        MedicineIdentifier::create([
            'medicine_product_id' => $productId,
            'medicine_ingredient_id' => $ingredientId,
            'system' => MedicineIdentifier::SYSTEM_RXNORM,
            'identifier_type' => 'rxcui',
            'value' => $concept->rxcui,
            'status' => 'active',
            'metadata' => ['provenance' => $this->provenance($concept, $method, $reason), 'mapping_history' => []],
        ]);
    }

    private function provenance(RxNormConcept $concept, string $method, string $reason): array
    {
        return [
            'rxcui' => $concept->rxcui,
            'release' => $concept->source_release,
            'method' => $method,
            'matched_fields' => $reason,
            'batch_id' => $concept->rxnorm_import_batch_id,
            'at' => now()->toIso8601String(),
        ];
    }

    private function retireIdentifier(RxNormConcept $concept, array &$counters): void
    {
        $identifier = MedicineIdentifier::where('system', MedicineIdentifier::SYSTEM_RXNORM)
            ->where('identifier_type', 'rxcui')
            ->where('value', $concept->rxcui)
            ->first();
        if ($identifier) {
            $meta = $identifier->metadata ?? [];
            $meta['retired_in_release'] = $concept->source_release;
            $meta['replaced_by_rxcui'] = $concept->replaced_by_rxcui;
            $identifier->update(['status' => 'retired', 'metadata' => $meta]);
        }
    }

    private function reconcileExisting(RxNormConcept $existing, array $record, RxNormImportBatch $batch, array &$counters): string
    {
        $existing->rxnorm_import_batch_id = $batch->id;
        $existing->retrieved_at = now();
        if ($existing->source_release !== $record['release'] && $record['release'] !== null) {
            $existing->source_release = $record['release'];
        }

        // Retirement transitions preserve everything and touch nothing else.
        $wasRetired = $existing->status === 'retired';
        $isRetired = ($record['status'] ?? 'active') === 'retired';
        if ($isRetired && ! $wasRetired) {
            $existing->status = 'retired';
            $existing->replaced_by_rxcui = $record['replaced_by_rxcui'];
            $existing->save();
            $this->retireIdentifier($existing, $counters);
            $counters['updated']++;

            return 'updated';
        }

        // Name/TTY drift on an already-linked concept is data, not a remap:
        // record it, keep the link (re-match only unlinked rows). A stable
        // link re-observed is by definition an exact identifier match.
        $dirty = $existing->isDirty();
        $existing->save();
        if ($existing->medicine_product_id === null && $existing->medicine_ingredient_id === null
            && $existing->match_status !== RxNormConcept::MATCH_AMBIGUOUS) {
            return $this->matchConcept($existing->fresh(), $counters);
        }
        if ($existing->match_status !== RxNormConcept::MATCH_AMBIGUOUS) {
            $existing->update([
                'match_status' => RxNormConcept::MATCH_EXACT,
                'match_detail' => 'Stable link re-observed across imports',
            ]);
        }
        $counters[$dirty ? 'updated' : 'unchanged']++;

        return $dirty ? 'updated' : 'unchanged';
    }

    private function parseSourceStrength(RxNormConcept $concept): ?array
    {
        $value = trim((string) ($concept->raw_payload['strength_value'] ?? ''));
        $unit = trim((string) ($concept->raw_payload['strength_unit'] ?? ''));
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return [(float) $value, $unit !== '' ? substr($unit, 0, 20) : null];
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

    private function pipeList(mixed $value): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => trim((string) $p),
            explode('|', (string) ($value ?? ''))
        )));
    }
}
