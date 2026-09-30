<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\TenantBackupKey;
use App\Services\Backup\EncryptionService;
use Tests\TestCase;

class TenantBackupTest extends TestCase
{
    public function test_dek_generated_per_tenant()
    {
        $svc = app(EncryptionService::class);
        TenantBackupKey::whereIn('tenant_id', [88001, 88002])->delete();

        $dek1 = $svc->getTenantDek(88001);
        $dek2 = $svc->getTenantDek(88002);

        $this->assertNotEquals($dek1, $dek2);
        $this->assertEquals(32, strlen($dek1));
    }

    public function test_dek_reused_for_same_tenant()
    {
        $svc = app(EncryptionService::class);
        TenantBackupKey::where('tenant_id', 88003)->delete();

        $a = $svc->getTenantDek(88003);
        $b = $svc->getTenantDek(88003);
        $this->assertEquals($a, $b);
    }

    public function test_encrypt_decrypt_roundtrip()
    {
        $svc = app(EncryptionService::class);
        TenantBackupKey::where('tenant_id', 88004)->delete();

        $src = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($src, 'Tenant data for encryption');
        $enc = $src . '.enc';
        $dec = $src . '.dec';

        $hmac = $svc->encryptFile($src, $enc, 88004);
        $svc->decryptFile($enc, $dec, 88004, $hmac);

        $this->assertEquals('Tenant data for encryption', file_get_contents($dec));

        @unlink($src); @unlink($enc); @unlink($dec);
    }

    public function test_tampered_file_rejected()
    {
        $svc = app(EncryptionService::class);
        TenantBackupKey::where('tenant_id', 88005)->delete();

        $src = tempnam(sys_get_temp_dir(), 'src');
        // Payload long enough that byte 50 sits inside the ciphertext.
        file_put_contents($src, 'Original tenant payload for tamper test');
        $enc = $src . '.enc';
        $hmac = $svc->encryptFile($src, $enc, 88005);

        // Tamper
        $data = file_get_contents($enc);
        $data[50] = chr(ord($data[50]) ^ 1);
        file_put_contents($enc, $data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/TAMPER DETECTED/');
        $svc->decryptFile($enc, $src . '.dec', 88005, $hmac);

        @unlink($src); @unlink($enc);
    }

    public function test_wrong_hmac_rejected()
    {
        $svc = app(EncryptionService::class);
        TenantBackupKey::where('tenant_id', 88006)->delete();

        $src = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($src, 'Data');
        $enc = $src . '.enc';
        $svc->encryptFile($src, $enc, 88006);

        $this->expectException(\RuntimeException::class);
        $svc->decryptFile($enc, $src . '.dec', 88006, str_repeat('0', 64));

        @unlink($src); @unlink($enc);
    }
}
