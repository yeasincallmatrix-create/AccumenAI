<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 124-B — Query Fingerprinting (never stores bound values)
 */
class QueryFingerprintService
{
    public function normalize(string $sql): string
    {
        // Lowercase, trim, remove extra whitespace
        $sql = strtolower(trim($sql));
        // Replace quoted strings with ?
        $sql = preg_replace("/'[^']*'/", "'?'", $sql);
        $sql = preg_replace('/"[^"]*"/', '"?"', $sql);
        // Replace numbers with ?
        $sql = preg_replace('/\b\d+\b/', '?', $sql);
        // Replace IN (?) lists
        $sql = preg_replace('/in\s*\(\s*\?(\s*,\s*\?)*\s*\)/', 'in (?)', $sql);
        // Collapse whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);
        return trim($sql);
    }

    public function fingerprint(string $sql): string
    {
        return hash('sha256', $this->normalize($sql));
    }

    public function record(string $sql, float $durationMs): void
    {
        $fingerprint = $this->fingerprint($sql);
        $normalized = $this->normalize($sql);

        $existing = DB::table('query_fingerprints')->where('fingerprint', $fingerprint)->first();

        if ($existing) {
            DB::table('query_fingerprints')->where('fingerprint', $fingerprint)->update([
                'execution_count' => $existing->execution_count + 1,
                'total_duration' => $existing->total_duration + $durationMs,
                'average_duration' => ($existing->total_duration + $durationMs) / ($existing->execution_count + 1),
                'maximum_duration' => max($existing->maximum_duration, $durationMs),
                'last_seen' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('query_fingerprints')->insert([
                'fingerprint' => $fingerprint,
                'normalized_query' => substr($normalized, 0, 65535),
                'execution_count' => 1,
                'total_duration' => $durationMs,
                'average_duration' => $durationMs,
                'maximum_duration' => $durationMs,
                'first_seen' => now(),
                'last_seen' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function top(int $limit = 10, string $by = 'count'): array
    {
        $query = DB::table('query_fingerprints');
        if ($by === 'duration') {
            $query->orderByDesc('total_duration');
        } else {
            $query->orderByDesc('execution_count');
        }
        return $query->limit($limit)->get()->all();
    }

    public function all(): array
    {
        return DB::table('query_fingerprints')->orderByDesc('execution_count')->get()->all();
    }
}
