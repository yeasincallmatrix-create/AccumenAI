<?php

namespace App\Services\Medical;

use Illuminate\Support\Facades\Http;

/**
 * Phase 12 — thin RxNav REST adapter (NLM/LHNCBC, no auth required for the
 * RxNorm vocabulary; monthly releases; JSON via .json suffix).
 *
 * Live verified 2026-09-10 (dataset 08-Sep-2026, API 3.1.355). Every call is
 * timeout-guarded and soft-fails (ok=false) — the file-driven import never
 * depends on the network, and no sync path calls this client by default.
 * NLM requests attribution for products using its data (see docs).
 */
final class RxNormClient
{
    public const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';

    public function __construct(
        protected string $baseUrl = self::BASE_URL,
        protected int $timeoutSeconds = 10
    ) {}

    /**
     * @return array{ok: bool, version?: string|null, apiVersion?: string|null, error?: string}
     */
    public function version(): array
    {
        $res = $this->get('/version.json');
        if (! $res['ok']) {
            return $res;
        }
        $json = $res['json'];

        return [
            'ok' => true,
            'version' => $json['version'] ?? null,
            'apiVersion' => $json['apiVersion'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, rxcui?: string|null, name?: string|null, tty?: string|null, error?: string}
     */
    public function findByString(string $name): array
    {
        $res = $this->get('/rxcui.json', ['name' => trim($name)]);
        if (! $res['ok']) {
            return $res;
        }
        $group = $res['json']['idGroup'] ?? [];
        $rxnorm = $group['rxnormId'] ?? null;
        $rxcui = is_array($rxnorm) ? ($rxnorm[0] ?? null) : $rxnorm;
        if (! $rxcui) {
            return ['ok' => true, 'rxcui' => null];
        }
        $props = $this->properties((string) $rxcui);

        return [
            'ok' => true,
            'rxcui' => (string) $rxcui,
            'name' => $props['name'] ?? null,
            'tty' => $props['tty'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, name?: string|null, tty?: string|null, synonym?: string|null, error?: string}
     */
    public function properties(string $rxcui): array
    {
        $res = $this->get("/rxcui/{$rxcui}/allProperties.json", ['prop' => 'all']);
        if (! $res['ok']) {
            return $res;
        }
        $group = $res['json']['propConceptGroup']['propConcept'] ?? [];
        $first = is_array($group) && array_is_list($group) ? ($group[0] ?? []) : $group;

        return [
            'ok' => true,
            'name' => $first['propValue'] ?? null,
            'tty' => $first['propName'] ?? null,
            'synonym' => $first['propValue'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, status?: string|null, error?: string}
     */
    public function historyStatus(string $rxcui): array
    {
        $res = $this->get("/rxcui/{$rxcui}/historystatus.json");
        if (! $res['ok']) {
            return $res;
        }
        $group = $res['json']['rxcuiStatusHistory'] ?? [];

        return ['ok' => true, 'status' => $group['status'] ?? null];
    }

    private function get(string $path, array $query = []): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get($this->baseUrl.$path, $query);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'RxNav unreachable: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'RxNav responded '.$response->status().'.'];
        }

        $json = $response->json();
        if (! is_array($json)) {
            return ['ok' => false, 'error' => 'RxNav returned a non-JSON payload.'];
        }

        return ['ok' => true, 'json' => $json];
    }
}
