<?php

namespace App\Services\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabDeviceCredential;
use Illuminate\Support\Str;

class DeviceAuthService
{
    /**
     * Verify a device token and return the bound analyzer.
     * Accepts the current token plus the previous token inside the
     * 24h rotation grace window. Updates last_used_at + last_ip on success.
     */
    public function authenticate(string $plainToken, ?string $ip = null): ?LabAnalyzer
    {
        // Try current + grace via model helper (prefix lookup + hash match).
        $candidate = LabDeviceCredential::findByTokenWithGrace($plainToken);

        if (! $candidate) {
            return null;
        }

        // Check revoked / expired
        if (! $candidate->isActive()) {
            return null;
        }

        // Check analyzer active
        $analyzer = $candidate->analyzer;
        if (! $analyzer || $analyzer->status !== 'active' || ! $analyzer->is_enabled) {
            return null;
        }

        // Update usage metadata (best-effort)
        try {
            $candidate->update([
                'last_used_at' => now(),
                'last_ip' => $ip,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('DeviceAuthService: failed to update last_used', [
                'credential_id' => $candidate->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $analyzer;
    }

    /**
     * Generate + persist a new credential for an analyzer.
     * Returns the plaintext token exactly once.
     */
    public function issue(LabAnalyzer $analyzer, array $abilities = ['result:post', 'worklist:get', 'health:post']): string
    {
        $plain = Str::random(48);
        $prefix = substr($plain, 0, 12);

        LabDeviceCredential::updateOrCreate(
            ['analyzer_id' => $analyzer->id],
            [
                'institute_id' => $analyzer->institute_id,
                'token_hash' => hash('sha256', $plain),
                'token_prefix' => $prefix,
                'name' => 'Gateway for '.$analyzer->name,
                'abilities' => $abilities,
                'rotated_at' => now(),
                'revoked_at' => null,
                'expires_at' => now()->addYear(),
            ]
        );

        return $plain;
    }

    /**
     * Rotate credential: the previous token stays valid for a 24h grace
     * period (server-enforced via previous_token_expires_at) so gateways
     * can roll over without downtime.
     */
    public function rotate(LabAnalyzer $analyzer): string
    {
        $existing = LabDeviceCredential::where('analyzer_id', $analyzer->id)->first();

        $plain = Str::random(48);
        $prefix = substr($plain, 0, 12);

        if ($existing) {
            // Preserve previous token for grace period
            $existing->update([
                'previous_token_hash' => $existing->token_hash,
                'previous_token_prefix' => $existing->token_prefix,
                'previous_token_expires_at' => now()->addHours(LabDeviceCredential::GRACE_PERIOD_HOURS),
                'token_hash' => hash('sha256', $plain),
                'token_prefix' => $prefix,
                'rotated_at' => now(),
            ]);
        } else {
            return $this->issue($analyzer, ['result:post', 'worklist:get', 'health:post']);
        }

        return $plain;
    }

    /**
     * Revoke immediately.
     */
    public function revoke(LabAnalyzer $analyzer): void
    {
        LabDeviceCredential::where('analyzer_id', $analyzer->id)->update([
            'revoked_at' => now(),
        ]);
    }
}
