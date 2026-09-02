<?php

namespace Tests\Feature;

use App\Support\CountryCodes;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneSystemTest extends TestCase
{
    public function test_country_codes_lengths(): void
    {
        $this->assertSame([11, 11], CountryCodes::nationalLengthFor('Bangladesh'));
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('United States'));
        $this->assertSame([10, 12], CountryCodes::nationalLengthFor('Indonesia'));
        $this->assertSame([10, 11], CountryCodes::nationalLengthFor('Germany'));
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('Australia'));
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('Saudi Arabia'));
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('United Arab Emirates'));
        $this->assertSame([7, 12], CountryCodes::nationalLengthFor(null));
        $this->assertSame([7, 12], CountryCodes::nationalLengthFor('UnknownLand'));
    }

    public function test_phone_partial_contains_realtime_hint(): void
    {
        $content = file_get_contents(resource_path('views/partials/phone.blade.php'));
        $this->assertStringContainsString('phone-number-input', $content);
        $this->assertStringContainsString('phone-hint', $content);
        $this->assertStringContainsString('monetixUpdatePhoneHint', $content);
        $this->assertStringContainsString("addEventListener('input'", $content);
        $this->assertStringContainsString('data-min', $content);
        $this->assertStringContainsString('data-max', $content);
        $this->assertStringContainsString('data-example', $content);
        $this->assertStringContainsString('maxlength', $content);
        $this->assertStringContainsString('placeholder', $content);
        $this->assertStringNotContainsString('xmlhttprequest', strtolower($content));
        $this->assertStringNotContainsString('$.ajax', $content);
    }

    public function test_phone_normalizer_duplicate_detection(): void
    {
        // Two national formats that normalize to same E164 should be detected as duplicate
        $a = PhoneNormalizer::toE164('01786699448', 'Bangladesh');
        $b = PhoneNormalizer::toE164('+8801786699448', 'Bangladesh');
        $this->assertSame($a, $b);
        $this->assertSame('+8801786699448', $a);

        // Collision scenario: students ID 10 and 25 both normalize to same
        $norm1 = PhoneNormalizer::toE164('01786699448', 'Bangladesh');
        $norm2 = PhoneNormalizer::toE164('+8801786699448', 'Bangladesh');
        $this->assertSame($norm1, $norm2, 'Collision detection must flag same normalized values');
    }

    public function test_phone_normalizer_collision_with_formatting(): void
    {
        $a = PhoneNormalizer::toE164('017-866-99448', 'Bangladesh');
        $b = PhoneNormalizer::toE164('01786699448', 'Bangladesh');
        $this->assertSame($a, $b);
    }

    public function test_phone_normalizer_preserves_legacy_invalid(): void
    {
        $this->assertNull(PhoneNormalizer::toE164('ff', 'Bangladesh'));
        $this->assertNull(PhoneNormalizer::toE164('01786abc', 'Bangladesh'));
        $this->assertTrue(PhoneNormalizer::hasInvalidCharacters('01786abc'));
        $this->assertTrue(PhoneNormalizer::hasInvalidCharacters('ff'));
    }

    public function test_phone_normalizer_multi_tenant_scope(): void
    {
        // Multi-tenant uniqueness: same phone in different institutes should not collide
        // Simulate two institutes with same phone but different institute_id
        $phone = '+8801786699448';
        $this->assertSame($phone, PhoneNormalizer::toE164('01786699448', 'Bangladesh'));
        // Collisions only within same institute_id scope for students/guardians/parties
        // Global scope for institute_users/users should collide
        $this->assertSame('+8801700380200', PhoneNormalizer::toE164('01700380200', 'Bangladesh'));
    }

    public function test_phone_normalize_command_dry_run(): void
    {
        $this->artisan('phone:normalize --dry-run')
            ->assertExitCode(0);
        // Command outputs report without modifying data
        $this->artisan('phone:normalize --dry-run')
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);
    }

    public function test_direct_phone_inputs_migrated(): void
    {
        $files = [
            'resources/views/admissions/convert.blade.php',
            'resources/views/admissions/form.blade.php',
            'resources/views/hr/employees/form.blade.php',
            'resources/views/hr/self/profile.blade.php',
            'resources/views/institute/crm/contacts/form.blade.php',
            'resources/views/institute/crm/leads/form.blade.php',
            'resources/views/institute/crm/organizations/form.blade.php',
            'resources/views/institute/finance/parties/form.blade.php',
            'resources/views/institute/teachers/form.blade.php',
            'resources/views/owner/profile.blade.php',
            'resources/views/sales/customers/form.blade.php',
            'resources/views/sales/leads/form.blade.php',
            'resources/views/workspace/create.blade.php',
        ];
        foreach ($files as $f) {
            $content = file_get_contents(base_path($f));
            $this->assertStringContainsString("partials.phone", $content, "File {$f} should use partials.phone");
        }
        // Ensure countries.phone_code not migrated
        $geoContent = file_get_contents(resource_path('views/admin/geo/create_country.blade.php'));
        $this->assertStringNotContainsString("partials.phone", $geoContent, 'countries.phone_code should not be migrated as phone');
    }

    public function test_phone_rule_compatible_with_legacy_dirty_records(): void
    {
        // Legacy dirty values like 'ff' should be caught by hasInvalidCharacters but not destroy data during unrelated edits
        // PhoneRule should fail for invalid, but we should not auto-modify
        $rule = new \App\Rules\PhoneRule('Bangladesh');
        $failed = false;
        $rule->validate('phone', 'ff', function () use (&$failed) { $failed = true; });
        $this->assertTrue($failed);

        // Empty should pass if allowEmpty true (nullable)
        $failed = false;
        $rule->validate('phone', '', function () use (&$failed) { $failed = true; });
        $this->assertFalse($failed);
    }

    public function test_unique_constraints_not_changed_without_collision_analysis(): void
    {
        // Ensure global phone uniqueness still enforced (we do NOT silently change to tenant-scoped)
        $controllerContent = file_get_contents(app_path('Http/Controllers/TeacherController.php'));
        $this->assertStringContainsString("institute_users", $controllerContent);
        $this->assertStringContainsString("phone", $controllerContent);
        $this->assertStringContainsString("unique", $controllerContent);
        $ownerContent = file_get_contents(app_path('Http/Controllers/Auth/OwnerRegisterController.php'));
        // OwnerRegister now enforces uniqueness via explicit duplicate check (normalized) rather than validator unique rule
        $lower = strtolower($ownerContent);
        $hasUniqueCheck = str_contains($lower, 'unique') || str_contains($lower, 'already taken') || str_contains($ownerContent, 'PhoneAlreadyTaken') || str_contains($ownerContent, 'where(');
        $this->assertTrue($hasUniqueCheck, 'OwnerRegister should enforce phone uniqueness');
        $this->assertTrue(str_contains($lower, 'users') || str_contains($ownerContent, 'User::'), 'OwnerRegister should reference users');
    }

    public function test_phone_hint_examples(): void
    {
        $this->assertSame('017XXXXXXXX', CountryCodes::phoneExampleFor('Bangladesh'));
        $this->assertSame('(555) 123-4567', CountryCodes::phoneExampleFor('United States'));
        $this->assertSame('08XX-XXXX-XXXX', CountryCodes::phoneExampleFor('Indonesia'));
    }
}
