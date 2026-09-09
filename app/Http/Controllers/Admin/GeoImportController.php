<?php

namespace App\Http\Controllers\Admin;

use App\Geo\Providers\LocalPackageProvider;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\GeoImport;
use App\Services\GeoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Super Admin importer for curated country geography packages.
 *
 * Upload a single package file (.jsonl / .json / .csv), pick target country
 * and mode, then drive the import via three AJAX endpoints:
 *
 *   POST geo/imports                → store the file, create the history row
 *   POST geo/imports/{import}/run  → process the next resumable batch
 *   POST geo/imports/{import}/validate → dry-run the whole package
 *
 * The engine is the shared GeoImportService (same code the CLI uses), so the
 * behaviour here matches `geo:import-package` exactly.
 *
 * Authorization: `auth:platform_admin` group (PlatformAdmin is the implicit
 * superuser in this application).
 */
class GeoImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = GeoImport::query()
            ->with(['country', 'creator'])
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.geo.imports', [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso2']),
            'imports' => $imports,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'file' => ['required', 'file'],
            'mode' => ['nullable', 'string', 'in:upsert,add'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $allowed = (array) config('geo.import.allowed_extensions', ['jsonl', 'json', 'csv', 'ndjson']);
        if (! in_array($extension, $allowed, true)) {
            return $this->importResponse(false, 'Unsupported file type. Allowed: '.implode(', ', $allowed).'.');
        }

        $maxKb = (int) config('geo.import.max_file_kb', 102400);
        if ($file->getSize() > $maxKb * 1024) {
            return $this->importResponse(false, "File exceeds the {$maxKb} KB upload limit.");
        }

        $import = new GeoImport([
            'country_id' => $data['country_id'],
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'format' => $extension,
            'status' => 'pending',
            'mode' => $data['mode'] ?? 'upsert',
            'created_by' => $request->user()?->id ?? auth('platform_admin')->id(),
        ]);

        $import->save();

        $storedPath = $file->storeAs('geo-imports/'.$import->id, $file->getClientOriginalName(), 'local');

        return $this->importResponse(true, 'Uploaded. Ready to run.', [
            'import' => $this->importPayload($import),
            'path' => $storedPath,
        ]);
    }

    /** Dry-run the package without writing (mode stays read-only). */
    public function validatePackage(Request $request, GeoImport $import): JsonResponse
    {
        if (! Storage::disk('local')->exists($this->storedRelativePath($import))) {
            return $this->importResponse(false, 'Package file not found on server. Please re-upload.');
        }
        $provider = $this->providerFor($import);
        $service = GeoImportService::fromConfig();

        try {
            // Phase 2 preview: validate + read-only impact analysis.
            $report = $service->preview($provider, $import->country);
        } catch (\Throwable $e) {
            return $this->importResponse(false, 'Validation failed: '.$e->getMessage());
        }

        $import->forceFill([
            'mode' => 'validate',
            'status' => $report['errors'] > 0 ? 'failed' : 'validated',
            'total_records' => $report['total'],
            'inserted_records' => $report['inserted'],
            'updated_records' => $report['updated'],
            'skipped_records' => $report['skipped'],
            'duplicate_count' => $report['duplicates'],
            'error_count' => $report['errors'],
            'error_summary' => $report['error_summary'],
            'completed_at' => now(),
        ])->save();

        // Success = validation completed (even with row errors) so the UI
        // can always render the impact preview; row problems live in the
        // preview payload and the import status.
        return $this->importResponse(true, 'Validation finished.', [
            'import' => $this->importPayload($import),
            'preview' => $report['preview'] ?? null,
        ]);
    }

    /**
     * Process the next resumable batch. Returns the running report so the UI
     * can keep polling until status is completed/failed.
     */
    public function run(Request $request, GeoImport $import): JsonResponse
    {
        if (in_array($import->status, ['completed', 'failed', 'validated'], true) && $import->status !== 'importing' && $import->total_records > 0 && $import->status === 'validated') {
            // Allow validated -> importing transition
        }
        if (in_array($import->status, ['completed', 'failed', 'rolled_back'], true)) {
            return $this->importResponse(true, 'Import already finished.', $this->importPayload($import));
        }

        if (! Storage::disk('local')->exists($this->storedRelativePath($import))) {
            return $this->importResponse(false, 'Package file not found. Please re-upload.');
        }

        $provider = $this->providerFor($import);
        $service = GeoImportService::fromConfig();

        try {
            $report = $service->runBatch(
                $import,
                $provider,
                (int) config('geo.import.records_per_request', 2000)
            );
        } catch (\Throwable $e) {
            return $this->importResponse(false, 'Import failed: '.$e->getMessage());
        }

        return $this->importResponse(true, 'Batch processed.', ['import' => $this->importPayload($import)]);
    }

    /** Current progress of an import (used for the polling UI). */
    public function status(Request $request, GeoImport $import): JsonResponse
    {
        return $this->importResponse(true, 'ok', ['import' => $this->importPayload($import)]);
    }

    /**
     * Phase 3: undo a completed (or partially failed) import using its
     * snapshot trail. Re-running a rolled-back import is blocked — upload
     * a fresh package instead.
     */
    public function rollback(Request $request, GeoImport $import): JsonResponse
    {
        if (! in_array($import->status, ['completed', 'failed'], true)) {
            return $this->importResponse(false, 'Only completed or failed imports can be rolled back.');
        }

        try {
            $result = GeoImportService::fromConfig()->rollback($import);
        } catch (\Throwable $e) {
            return $this->importResponse(false, 'Rollback failed: '.$e->getMessage());
        }

        return $this->importResponse(true, 'Import rolled back.', [
            'import' => $this->importPayload($import->fresh()),
            'rollback' => $result,
        ]);
    }

    /** Phase 3: read-only counts shown before the type-to-confirm step. */
    public function clearPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);

        return $this->importResponse(true, 'ok', [
            'preview' => GeoImportService::fromConfig()->clearPreview($country),
        ]);
    }

    /**
     * Phase 3 danger zone: delete every administrative unit of a country.
     * Requires typing the exact country name as confirmation.
     */
    public function clear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'confirm' => ['required', 'string'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);

        if (trim($data['confirm']) !== trim($country->name)) {
            return $this->importResponse(false, 'Confirmation does not match the country name. Nothing was deleted.');
        }

        try {
            $result = GeoImportService::fromConfig()->clearCountry($country);
        } catch (\Throwable $e) {
            return $this->importResponse(false, 'Clear failed (nothing was deleted): '.$e->getMessage());
        }

        return $this->importResponse(true, "All geography rows for {$country->name} were deleted.", [
            'country' => $country->name,
            'result' => $result,
        ]);
    }

    /**
     * Download a minimal JSONL package template (division + district +
     * upazila with parent linkage) so admins see the exact expected shape.
     */
    public function template()
    {
        $lines = [
            ['level' => 1, 'code' => 'XX-DIVISION', 'name' => 'Example Division', 'parent_code' => null, 'postal_code' => null, 'latitude' => null, 'longitude' => null],
            ['level' => 2, 'code' => 'XX-DIVISION-DISTRICT', 'name' => 'Example District', 'parent_code' => 'XX-DIVISION', 'postal_code' => null, 'latitude' => null, 'longitude' => null],
            ['level' => 3, 'code' => 'XX-U1', 'name' => 'Example Upazila', 'parent_code' => 'XX-DIVISION-DISTRICT', 'postal_code' => '1230', 'latitude' => null, 'longitude' => null],
        ];

        $body = implode("\n", array_map(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE), $lines))."\n";

        return response($body, 200, [
            'Content-Type' => 'application/jsonl',
            'Content-Disposition' => 'attachment; filename="geo-package-template.jsonl"',
        ]);
    }

    /**
     * Convert a legacy flat file ({level_1, level_2, level_3, code,
     * postal_code} rows) into an import-ready JSONL download.
     *
     * No database writes: division/district codes are reused from existing
     * units (matched by name within the target country) so a later import
     * updates instead of duplicating; only genuinely new names get minted
     * `{ISO2}-...` codes.
     */
    public function convert(Request $request)
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'file' => ['required', 'file'],
        ]);

        $country = Country::query()->findOrFail($data['country_id']);

        $raw = (string) file_get_contents($request->file('file')->getRealPath());
        $rows = json_decode($raw, true);
        if (! is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'File is not valid JSON.'], 422);
        }
        if (isset($rows['level_1'])) {
            $rows = [$rows];
        }

        // Existing units by level+name for code reuse (duplicate-proofing).
        $existing = \App\Models\AdministrativeUnit::query()
            ->where('administrative_units.country_id', $country->id)
            ->join('administrative_levels as l', 'l.id', '=', 'administrative_units.administrative_level_id')
            ->select('administrative_units.code', 'administrative_units.name', 'l.level_number')
            ->get();
        $known = [];
        foreach ($existing as $unit) {
            $known[(int) $unit->level_number][$this->slugify($unit->name)] = (string) $unit->code;
        }

        $prefix = strtoupper((string) $country->iso2);
        $divCodes = [];
        $distCodes = [];
        $lines = [];
        $skipped = 0;

        $codeFor = function (int $level, string $name, ?string $parentCode, ?string $fallback) use (&$known, $prefix) {
            $slug = $this->slugify($name);
            if (isset($known[$level][$slug])) {
                return $known[$level][$slug];
            }
            $minted = $fallback ?? ($prefix.'-'.strtoupper($slug));
            $known[$level][$slug] = $minted;

            return $minted;
        };

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }
            $l1 = trim((string) ($row['level_1'] ?? ''));
            $l2 = trim((string) ($row['level_2'] ?? ''));
            $l3 = trim((string) ($row['level_3'] ?? ''));
            if ($l1 === '' || $l2 === '' || $l3 === '') {
                $skipped++;
                continue;
            }
            $divCode = $codeFor(1, $l1, null, null);
            $distCode = $codeFor(2, $l2, $divCode, null);
            // Remember division/district rows once.
            if (! isset($divCodes[$divCode])) {
                $divCodes[$divCode] = true;
                $lines['div'][] = ['level' => 1, 'code' => $divCode, 'name' => $l1, 'parent_code' => null, 'postal_code' => null, 'latitude' => null, 'longitude' => null];
            }
            if (! isset($distCodes[$distCode])) {
                $distCodes[$distCode] = true;
                $lines['dist'][] = ['level' => 2, 'code' => $distCode, 'name' => $l2, 'parent_code' => $divCode, 'postal_code' => null, 'latitude' => null, 'longitude' => null];
            }
            $upCode = trim((string) ($row['code'] ?? ''));
            if ($upCode === '') {
                $upCode = $distCode.'-'.$this->slugify($l3);
                $upCode = strtoupper($upCode);
            }
            $postal = trim((string) ($row['postal_code'] ?? ''));
            $lines['up'][] = [
                'level' => 3,
                'code' => $upCode,
                'name' => $l3,
                'parent_code' => $distCode,
                'postal_code' => $postal !== '' ? $postal : null,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        $ordered = array_merge($lines['div'] ?? [], $lines['dist'] ?? [], $lines['up'] ?? []);
        if (empty($ordered)) {
            return response()->json(['success' => false, 'message' => 'No convertible rows found. Expect objects with level_1, level_2 and level_3 keys.'], 422);
        }

        $body = implode("\n", array_map(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE), $ordered))."\n";
        $orig = pathinfo((string) $request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);

        return response($body, 200, [
            'Content-Type' => 'application/jsonl',
            'Content-Disposition' => 'attachment; filename="converted-'.$orig.'.jsonl"',
            'X-Convert-Total' => (string) count($ordered),
            'X-Convert-Skipped' => (string) $skipped,
        ]);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    private function providerFor(GeoImport $import): LocalPackageProvider
    {
        $absolute = Storage::disk('local')->path($this->storedRelativePath($import));

        // Resumable: continue reading after the last consumed record.
        return new LocalPackageProvider($absolute, (int) $import->total_records);
    }

    private function storedRelativePath(GeoImport $import): string
    {
        return 'geo-imports/'.$import->id.'/'.$import->filename;
    }

    private function importPayload(GeoImport $import): array
    {
        $import->load('country');

        return [
            'id' => $import->id,
            'country' => $import->country?->name,
            'country_iso2' => $import->country?->iso2,
            'filename' => $import->filename,
            'format' => $import->format,
            'status' => $import->status,
            'mode' => $import->mode,
            'total_records' => $import->total_records,
            'inserted_records' => $import->inserted_records,
            'updated_records' => $import->updated_records,
            'skipped_records' => $import->skipped_records,
            'duplicate_count' => $import->duplicate_count,
            'error_count' => $import->error_count,
            'error_summary' => $import->error_summary,
            'created_at' => $import->created_at?->toDateTimeString(),
            'completed_at' => $import->completed_at?->toDateTimeString(),
        ];
    }

    private function importResponse(bool $success, string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $success ? 200 : 422);
    }
}
