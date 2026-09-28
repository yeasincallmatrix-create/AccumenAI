<?php

namespace App\Services\Dealership\Api;

use Illuminate\Support\Facades\DB;

/**
 * Generates the developer-facing API doc structure from the endpoint
 * registry (per institute).
 */
class ApiDocGenerator
{
    public function generate(int $instituteId): array
    {
        $endpoints = DB::table('dealership_api_endpoints')
            ->where('institute_id', $instituteId)
            ->orderBy('version')
            ->orderBy('endpoint_key')
            ->get();

        $byVersion = [];
        foreach ($endpoints as $ep) {
            $byVersion[$ep->version][] = [
                'key' => $ep->endpoint_key,
                'method' => $ep->http_method,
                'uri' => $ep->uri,
                'description' => $ep->description,
                'required_permission' => $ep->required_permission,
                'is_enabled' => (bool) $ep->is_enabled,
            ];
        }

        return [
            'version' => config('dealership.api.api_version', 'v1'),
            'generated_at' => now()->toIso8601String(),
            'endpoint_count' => $endpoints->count(),
            'versions' => $byVersion,
        ];
    }
}
