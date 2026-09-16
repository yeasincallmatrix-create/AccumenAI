<?php

namespace App\Console\Commands;

use App\Models\Medical\DgdaMedicine;
use App\Models\Medical\DgdaSyncBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDgdaRegistryBulk extends Command
{
    protected $signature = 'medical:dgda-import-bulk
        {file : Path to CSV or JSON file}
        {--country=BD : Country code}
        {--chunk=500 : Batch size}';

    protected $description = 'Bulk import DGDA registry data from CSV or JSON';

    public function handle(): int
    {
        $file = $this->argument('file');
        $country = $this->option('country');
        $chunkSize = (int) $this->option('chunk');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return 1;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $batch = DgdaSyncBatch::create([
            'batch_type' => 'bulk_import',
            'source_file' => basename($file),
            'status' => 'running',
            'started_at' => now(),
            'started_by' => auth()->id(),
        ]);

        try {
            $rows = match ($ext) {
                'csv', 'txt' => $this->readCsv($file),
                'json' => $this->readJson($file),
                default => throw new \RuntimeException("Unsupported file type: {$ext}"),
            };

            $batch->update(['total_rows' => count($rows)]);
            $this->info("Parsed {$batch->total_rows} rows.");

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $failed = 0;
            $errors = [];
            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();

            $chunks = array_chunk($rows, $chunkSize);
            foreach ($chunks as $chunk) {
                DB::transaction(function () use ($chunk, $country, &$imported, &$updated, &$skipped, &$failed, &$errors) {
                    foreach ($chunk as $row) {
                        $dgdaCode = trim($row['dgda_code'] ?? '');
                        if ($dgdaCode === '') {
                            $skipped++;
                            continue;
                        }

                        $payload = [
                            'country_code' => $country,
                            'dar_number' => $row['dar_number'] ?? null,
                            'concept_id' => $row['concept_id'] ?? null,
                            'brand_name' => trim($row['brand_name'] ?? 'Unknown'),
                            'generic_name' => $row['generic_name'] ?? null,
                            'strength' => $row['strength'] ?? null,
                            'dosage_form' => $row['dosage_form'] ?? null,
                            'route' => $row['route'] ?? null,
                            'manufacturer' => $row['manufacturer'] ?? null,
                            'pack_size' => $row['pack_size'] ?? null,
                            'normalized_name' => DgdaMedicine::generateNormalized(
                                $row['brand_name'] ?? '',
                                $row['strength'] ?? null
                            ),
                            'status' => 'active',
                            'synced_at' => now(),
                        ];

                        try {
                            $existing = DgdaMedicine::where('dgda_code', $dgdaCode)->first();
                            if ($existing) {
                                $existing->update($payload);
                                $updated++;
                            } else {
                                DgdaMedicine::create(array_merge($payload, ['dgda_code' => $dgdaCode]));
                                $imported++;
                            }
                        } catch (\Throwable $e) {
                            $failed++;
                            $errors[] = "Code {$dgdaCode}: ".$e->getMessage();
                        }
                    }
                });
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();

            $batch->update([
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'status' => 'completed',
                'error_log' => empty($errors) ? null : implode("\n", array_slice($errors, 0, 100)),
                'completed_at' => now(),
            ]);

            $this->info("Imported: {$imported} | Updated: {$updated} | Skipped: {$skipped} | Failed: {$failed}");

            return 0;
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $this->error('Import failed: '.$e->getMessage());

            return 1;
        }
    }

    protected function readCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line)) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), null));
        }
        fclose($handle);

        return $rows;
    }

    protected function readJson(string $file): array
    {
        $data = json_decode(file_get_contents($file), true);
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON structure');
        }

        return $data['items'] ?? $data;
    }
}
