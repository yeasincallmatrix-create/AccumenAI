<?php

namespace App\Console\Commands;

use App\Models\Medical\DgdaMedicine;
use App\Models\Medical\DgdaSyncBatch;
use App\Services\Medical\DgdaService;
use Illuminate\Console\Command;

class SyncDgdaApi extends Command
{
    protected $signature = 'medical:dgda-sync-api
        {--code= : Single DGDA code to sync}
        {--limit=50 : Max codes per run}
        {--delay=1 : Seconds between requests}
        {--country=BD : Country code}';

    protected $description = 'Sync DGDA registry via API (code-by-code)';

    public function handle(DgdaService $dgda): int
    {
        $singleCode = $this->option('code');
        $country = $this->option('country');

        if ($singleCode) {
            return $this->syncSingle($dgda, $singleCode, $country);
        }

        $this->warn('Batch mode not yet implemented. Use --code=XXX for single sync.');

        return 1;
    }

    protected function syncSingle(DgdaService $dgda, string $code, string $country): int
    {
        $this->info("Fetching DGDA code: {$code}");

        $data = $dgda->lookupCode($code);
        if (empty($data) || ! ($data['ok'] ?? false)) {
            $this->error('No data returned for code: '.$code);

            return 1;
        }

        $raw = $data['raw'] ?? [];
        $display = $data['display'] ?? null;

        // Parse display string for brand/generic if available
        $brandName = $display ?? 'Unknown';
        $genericName = null;
        if ($display && str_contains($display, ' - ')) {
            $parts = explode(' - ', $display, 2);
            $brandName = trim($parts[0]);
            $genericName = trim($parts[1] ?? '');
        }

        $payload = [
            'country_code' => $country,
            'dgda_code' => $code,
            'dar_number' => $raw['parameter'][0]->valueString ?? null,
            'brand_name' => $brandName,
            'generic_name' => $genericName ?: null,
            'normalized_name' => DgdaMedicine::generateNormalized($brandName, null),
            'status' => 'active',
            'synced_at' => now(),
        ];

        DgdaMedicine::updateOrCreate(['dgda_code' => $code], $payload);
        $this->info("Synced: {$payload['brand_name']}");

        return 0;
    }
}
