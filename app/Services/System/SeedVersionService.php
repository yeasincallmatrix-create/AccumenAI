<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 108 — Seed Version Control
 */
class SeedVersionService
{
    public const SEEDS = [
        'roles' => ['table' => 'roles', 'key' => 'slug'],
        'permissions' => ['table' => 'permissions', 'key' => 'slug'],
        'modules' => ['table' => 'module_registry', 'key' => 'key'],
        'industries' => ['table' => 'industry_settings', 'key' => 'industry_key'],
        'themes' => ['table' => 'themes', 'key' => 'slug'],
        'countries' => ['table' => 'countries', 'key' => 'iso2'],
        'administrative_levels' => ['table' => 'administrative_levels', 'key' => 'id'],
    ];

    public function checksum(string $seedName): string
    {
        $config = self::SEEDS[$seedName] ?? null;
        if (! $config) return hash('sha256', $seedName);

        try {
            $rows = DB::table($config['table'])->orderBy($config['key'])->get();
            return hash('sha256', json_encode($rows));
        } catch (\Throwable $e) {
            return hash('sha256', $e->getMessage());
        }
    }

    public function record(string $seedName, string $version = '1'): \App\Models\SystemSeedVersion
    {
        $checksum = $this->checksum($seedName);

        return \App\Models\SystemSeedVersion::updateOrCreate(
            ['seed_name' => $seedName, 'version' => $version],
            ['checksum' => $checksum, 'executed_at' => now()]
        );
    }

    public function verify(): array
    {
        $results = [];
        foreach (self::SEEDS as $name => $cfg) {
            $current = $this->checksum($name);
            $stored = \App\Models\SystemSeedVersion::where('seed_name', $name)->latest('executed_at')->first();

            $healthy = $stored && $stored->checksum === $current;
            $exists = DB::table($cfg['table'])->count() > 0;

            $results[$name] = [
                'healthy' => $healthy && $exists,
                'exists' => $exists,
                'current_checksum' => $current,
                'stored_checksum' => $stored?->checksum,
                'version' => $stored?->version,
            ];
        }
        return $results;
    }

    public function verifyAll(): array
    {
        $results = $this->verify();
        $healthy = collect($results)->every(fn($r) => $r['healthy']);

        return [
            'healthy' => $healthy,
            'results' => $results,
            'missing' => collect($results)->filter(fn($r) => ! $r['healthy'])->keys()->all(),
        ];
    }

    public function recordAll(): void
    {
        foreach (array_keys(self::SEEDS) as $name) {
            $this->record($name);
        }
    }
}
