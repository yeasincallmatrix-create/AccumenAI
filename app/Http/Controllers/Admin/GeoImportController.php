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
        $provider = $this->providerFor($import);
        $service = GeoImportService::fromConfig();

        $report = $service->validate($provider, $import->country);

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

        return $this->importResponse($report['errors'] === 0, 'Validation finished.', ['import' => $this->importPayload($import)]);
    }

    /**
     * Process the next resumable batch. Returns the running report so the UI
     * can keep polling until status is completed/failed.
     */
    public function run(Request $request, GeoImport $import): JsonResponse
    {
        if (in_array($import->status, ['completed', 'failed'], true)) {
            return $this->importResponse(true, 'Import already finished.', $this->importPayload($import));
        }

        $provider = $this->providerFor($import);
        $service = GeoImportService::fromConfig();

        $report = $service->runBatch(
            $import,
            $provider,
            (int) config('geo.import.records_per_request', 2000)
        );

        return $this->importResponse(true, 'Batch processed.', ['import' => $this->importPayload($import)]);
    }

    /** Current progress of an import (used for the polling UI). */
    public function status(Request $request, GeoImport $import): JsonResponse
    {
        return $this->importResponse(true, 'ok', ['import' => $this->importPayload($import)]);
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
