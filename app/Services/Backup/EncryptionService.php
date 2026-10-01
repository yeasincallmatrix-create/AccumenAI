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
     * Chunked streaming encryption.
     * Format: [iv 12][tag 16][len 4][ciphertext] × N chunks
     * Peak disk: input + output (no triple temp files).
     * Returns HMAC-SHA256 (hex) over all chunk headers + ciphertexts.
     */
    public function encryptFile(string $src, string $dest, int $tenantId): string
    {
        $dek = $this->getTenantDek($tenantId);
        $hmacKey = $this->getHmacKey();
        $hmacCtx = hash_init('sha256', HASH_HMAC, $hmacKey);

        $in = fopen($src, 'rb');
        $out = fopen($dest, 'wb');
        if (!$in || !$out) {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            $this->wipe($dek);
            $this->wipe($hmacKey);
            throw new \RuntimeException('Cannot open files for encryption');
        }

        $chunkSize = 5 * 1024 * 1024; // 5 MB per chunk
        $chunkCount = 0;

        try {
            while (!feof($in)) {
                $chunk = fread($in, $chunkSize);
                if ($chunk === false) break;   // read error — stop (no infinite loop)
                if ($chunk === '') continue;   // empty read, feof not yet set

                $iv = random_bytes(self::IV_LEN);
                $tag = '';
                $ct = openssl_encrypt(
                    $chunk, self::CIPHER, $dek, OPENSSL_RAW_DATA,
                    $iv, $tag, '', self::TAG_LEN
                );

                if ($ct === false) {
                    throw new \RuntimeException("Chunk {$chunkCount} encryption failed");
                }

                // Write: [iv 12][tag 16][len 4][ciphertext N]
                $header = $iv . $tag . pack('N', strlen($ct));
                fwrite($out, $header);
                fwrite($out, $ct);

                // HMAC over header + ciphertext
                hash_update($hmacCtx, $header);
                hash_update($hmacCtx, $ct);

                $chunkCount++;
            }
        } finally {
            fclose($in);
            fclose($out);
            $this->wipe($dek);
            $this->wipe($hmacKey);
        }

        return hash_final($hmacCtx);
    }

    /**
     * Chunked streaming decryption with tamper detection.
     */
    public function decryptFile(string $src, string $dest, int $tenantId, string $expectedHmac): void
    {
        $hmacKey = $this->getHmacKey();

        // Layer 1: HMAC verification (streaming)
        $hmacCtx = hash_init('sha256', HASH_HMAC, $hmacKey);
        $in = fopen($src, 'rb');
        if (!$in) {
            $this->wipe($hmacKey);
            throw new \RuntimeException('Cannot open backup file for reading');
        }
        while (!feof($in)) {
            $chunk = fread($in, 5 * 1024 * 1024);
            if ($chunk === false) break;   // read error — stop (no infinite loop)
            if ($chunk === '') continue;
            hash_update($hmacCtx, $chunk);
        }
        fclose($in);

        $computed = hash_final($hmacCtx);
        $this->wipe($hmacKey);

        if (!hash_equals($expectedHmac, $computed)) {
            throw new \RuntimeException('TAMPER DETECTED: HMAC mismatch');
        }

        // Layer 2: Chunked decryption (GCM tag validates each chunk)
        $dek = $this->getTenantDek($tenantId);

        $in = fopen($src, 'rb');
        $out = fopen($dest, 'wb');
        if (!$in || !$out) {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            $this->wipe($dek);
            throw new \RuntimeException('Cannot open files for decryption');
        }

        try {
            while (!feof($in)) {
                $header = fread($in, self::IV_LEN + self::TAG_LEN + 4);
                if ($header === false) break;                      // read error
                if (strlen($header) < self::IV_LEN + self::TAG_LEN + 4) break; // clean EOF

                $iv = substr($header, 0, self::IV_LEN);
                $tag = substr($header, self::IV_LEN, self::TAG_LEN);
                $lenArr = unpack('N', substr($header, self::IV_LEN + self::TAG_LEN));
                $len = $lenArr[1];

                $ct = fread($in, $len);
                if ($ct === false || strlen($ct) !== $len) {
                    throw new \RuntimeException('Truncated chunk');
                }

                $pt = openssl_decrypt(
                    $ct, self::CIPHER, $dek, OPENSSL_RAW_DATA,
                    $iv, $tag
                );

                if ($pt === false) {
                    throw new \RuntimeException('TAMPER DETECTED: GCM tag invalid');
                }

                fwrite($out, $pt);
            }
        } finally {
            fclose($in);
            fclose($out);
            $this->wipe($dek);
        }
    }

    /**
     * Verify HMAC of an encrypted file without decrypting.
     * encryptFile() feeds HMAC with the exact byte stream it writes
     * ([iv|tag|len] + ciphertext per chunk), so a single hash_hmac over
     * the whole file yields the identical value.
     */
    public function verifyFileHmac(string $path, string $expectedHmac): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return false;
        }

        $hmacKey = $this->getHmacKey();
        $computed = hash_hmac('sha256', $data, $hmacKey);
        $this->wipe($hmacKey);

        return hash_equals($expectedHmac, $computed);
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
