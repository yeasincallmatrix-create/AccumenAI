<?php

namespace App\Services\Dealership\Api;

use App\Models\Dealership\ApiToken;
use App\Models\Dealership\SalesForce;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Per-SR device token issuance (complements Sanctum, which covers
 * Authenticatable users — SR rows are plain entity records).
 *
 * Only sha256 hashes are persisted; plaintext is returned once at
 * issuance and never stored.
 */
class ApiTokenService
{
    public function issueToken(SalesForce $salesForce, string $name, array $abilities = [], ?Carbon $expiresAt = null): array
    {
        $raw = Str::random(64);

        $token = ApiToken::create([
            'institute_id' => $salesForce->institute_id,
            'sales_force_id' => $salesForce->id,
            'token_hash' => hash('sha256', $raw),
            'name' => $name,
            'abilities' => $abilities !== [] ? $abilities : config('dealership.api.default_abilities', []),
            'expires_at' => $expiresAt ?? now()->addDays((int) config('dealership.api.token_ttl_days', 90)),
        ]);

        return ['plaintext' => $raw, 'token' => $token];
    }

    public function revokeToken(ApiToken $token): void
    {
        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }
    }

    public function validateToken(string $raw, int $instituteId): ?ApiToken
    {
        $token = ApiToken::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('token_hash', hash('sha256', $raw))
            ->first();

        if ($token === null || ! $token->isValid()) {
            return null;
        }

        $token->update(['last_used_at' => now()]);

        return $token->fresh();
    }
}
