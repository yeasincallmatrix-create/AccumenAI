<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 103 — Schema Version Protection
 */
class SchemaVersionService
{
    public function currentVersion(): string
    {
        // Use latest migration batch as version
        $latest = DB::table('migrations')->orderByDesc('batch')->orderByDesc('migration')->value('migration');
        return $latest ?? '0';
    }

    public function store(): \App\Models\SystemSchemaVersion
    {
        $version = $this->currentVersion();
        $dbVersion = $this->databaseVersion();
        $count = DB::table('migrations')->count();
        $checksum = $this->checksum();

        return \App\Models\SystemSchemaVersion::create([
            'version' => $version,
            'database_version' => $dbVersion,
            'laravel_version' => app()->version(),
            'migration_count' => $count,
            'checksum' => $checksum,
            'installed_at' => now(),
        ]);
    }

    public function databaseVersion(): string
    {
        try {
            $row = DB::selectOne('SELECT VERSION() as v');
            return $row->v ?? 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    public function checksum(): string
    {
        $migrations = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
        return hash('sha256', implode(',', $migrations));
    }

    public function compare(): array
    {
        $files = glob(database_path('migrations/*.php'));
        $all = array_map(fn($f) => basename($f, '.php'), $files);
        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = array_values(array_diff($all, $ran));

        $latestStored = \App\Models\SystemSchemaVersion::orderByDesc('installed_at')->first();
        $currentChecksum = $this->checksum();
        $storedChecksum = $latestStored?->checksum;

        $mismatch = ! empty($pending) || ($storedChecksum && $storedChecksum !== $currentChecksum);

        return [
            'current_version' => $this->currentVersion(),
            'stored_version' => $latestStored?->version,
            'current_checksum' => $currentChecksum,
            'stored_checksum' => $storedChecksum,
            'pending' => $pending,
            'pending_count' => count($pending),
            'mismatch' => $mismatch,
            'database_version' => $this->databaseVersion(),
            'laravel_version' => app()->version(),
        ];
    }

    public function isMismatch(): bool
    {
        return $this->compare()['mismatch'];
    }

    public function latest(): ?\App\Models\SystemSchemaVersion
    {
        return \App\Models\SystemSchemaVersion::orderByDesc('installed_at')->first();
    }
}
