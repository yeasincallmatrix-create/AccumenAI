<?php

namespace App\Services\Backup;

use App\Models\TenantBackupKey;
use Illuminate\Support\Facades\Crypt;

class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;
    private const DEK_LEN = 32;

    /**
     * Get or create tenant's DEK (encrypted with master KEK).
     */
    public function getTenantDek(int $tenantId): string
    {
        $record = TenantBackupKey::where('tenant_id', $tenantId)->first();

        if (!$record) {
            $dek = random_bytes(self::DEK_LEN);
            TenantBackupKey::create([
                'tenant_id'     => $tenantId,
                'encrypted_dek' => $this->wrapDek($dek),
            ]);
            return $dek;
        }

        return $this->unwrapDek($record->encrypted_dek);
    }

    /**
     * Wrap DEK with master KEK.
     */
    private function wrapDek(string $dek): string
    {
        $kek = $this->getMasterKek();
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $wrapped = openssl_encrypt($dek, self::CIPHER, $kek, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        if ($wrapped === false) {
            $this->wipe($kek);
            throw new \RuntimeException('DEK wrap failed');
        }

        $this->wipe($kek);

        return base64_encode($iv . $tag . $wrapped);
    }

    /**
     * Unwrap DEK with master KEK.
     */
    private function unwrapDek(string $encrypted): string
    {
        $kek = $this->getMasterKek();
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, self::IV_LEN);
        $tag = substr($data, self::IV_LEN, self::TAG_LEN);
        $wrapped = substr($data, self::IV_LEN + self::TAG_LEN);

        $dek = openssl_decrypt($wrapped, self::CIPHER, $kek, OPENSSL_RAW_DATA, $iv, $tag);

        $this->wipe($kek);

        if ($dek === false) {
            throw new \RuntimeException('DEK unwrap failed — key corruption');
        }

        return $dek;
    }

    /**
     * Encrypt file. Format: [iv 12][tag 16][ciphertext].
     * Returns HMAC for tamper detection.
     */
    public function encryptFile(string $src, string $dest, int $tenantId): string
    {
        $dek = $this->getTenantDek($tenantId);

        $plaintext = file_get_contents($src);
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        if ($ciphertext === false) {
            $this->wipe($dek);
            throw new \RuntimeException('Encryption failed');
        }

        $fileBytes = $iv . $tag . $ciphertext;
        file_put_contents($dest, $fileBytes);

        // HMAC using server-side secret (separate from DEK)
        $hmacKey = $this->getHmacKey();
        $hmac = hash_hmac('sha256', $fileBytes, $hmacKey);

        $this->wipe($dek);
        $this->wipe($hmacKey);

        return $hmac;
    }

    /**
     * Decrypt file with tamper detection.
     */
    public function decryptFile(string $src, string $dest, int $tenantId, string $expectedHmac): void
    {
        $fileBytes = file_get_contents($src);

        // Layer 1: HMAC check
        $hmacKey = $this->getHmacKey();
        $computed = hash_hmac('sha256', $fileBytes, $hmacKey);
        $this->wipe($hmacKey);

        if (!hash_equals($expectedHmac, $computed)) {
            throw new \RuntimeException('TAMPER DETECTED: HMAC mismatch');
        }

        // Layer 2: GCM decrypt (auth tag validates)
        $dek = $this->getTenantDek($tenantId);
        $iv = substr($fileBytes, 0, self::IV_LEN);
        $tag = substr($fileBytes, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($fileBytes, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $iv, $tag);
        $this->wipe($dek);

        if ($plaintext === false) {
            throw new \RuntimeException('TAMPER DETECTED: Decryption failed (GCM tag invalid)');
        }

        file_put_contents($dest, $plaintext);
    }

    private function getMasterKek(): string
    {
        $key = config('backup.master_key') ?? env('BACKUP_MASTER_KEY');

        if (empty($key)) {
            throw new \RuntimeException('BACKUP_MASTER_KEY not configured');
        }

        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new \RuntimeException('BACKUP_MASTER_KEY invalid (must be base64-encoded 32 bytes)');
        }

        return $decoded;
    }

    private function getHmacKey(): string
    {
        // Derive separate HMAC key from master KEK
        $kek = $this->getMasterKek();
        $derived = hash_hkdf('sha256', $kek, 32, 'backup-hmac');
        $this->wipe($kek);

        return $derived;
    }

    /**
     * Best-effort memory wipe. sodium_memzero() is unavailable on some
     * builds (e.g. XAMPP/Windows without ext-sodium), so fall back to
     * overwriting the buffer before releasing it.
     */
    private function wipe(string &$data): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($data);
            return;
        }

        $len = strlen($data);
        if ($len > 0) {
            $data = str_repeat("\0", $len);
        }
        $data = '';
    }
}
