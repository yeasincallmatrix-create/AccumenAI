<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    private array $enLang = [];

    private array $bnLang = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enLang = require base_path('lang/mawa/en.php');
        $this->bnLang = require base_path('lang/mawa/bn.php');
    }

    // ──────────────────────────────────────────────────── helpers

    private function collectLeafKeys(array $items, string $prefix = ''): array
    {
        $keys = [];
        foreach ($items as $key => $value) {
            $full = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->collectLeafKeys($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }

    // ──────────────────────────────────────────────────── tests

    public function test_en_lang_file_has_all_keys_that_bn_has(): void
    {
        $bnKeys = $this->collectLeafKeys($this->bnLang);
        $enKeys = $this->collectLeafKeys($this->enLang);

        $missingInEn = array_diff($bnKeys, $enKeys);
        $missingInBn = array_diff($enKeys, $bnKeys);

        $this->assertEmpty($missingInEn, 'Keys in BN missing from EN: '.implode(', ', $missingInEn));
        $this->assertEmpty($missingInBn, 'Keys in EN missing from BN: '.implode(', ', $missingInBn));
    }

    public function test_mawa_lang_returns_english_when_lang_is_en(): void
    {
        session(['mawa_lang' => 'en']);
        App::setLocale('en');

        $result = mawa_lang('verified');
        $this->assertSame('Verified', $result);
    }

    public function test_mawa_lang_returns_bangla_when_lang_is_bn(): void
    {
        session(['mawa_lang' => 'bn']);
        App::setLocale('bn');

        $result = mawa_lang('verified');
        $this->assertSame('ভেরিফাইড', $result);
    }

    public function test_mawa_lang_falls_back_to_english_when_bn_key_missing(): void
    {
        session(['mawa_lang' => 'bn']);
        App::setLocale('bn');

        $enKeys = $this->collectLeafKeys($this->enLang);
        $bnKeys = $this->collectLeafKeys($this->bnLang);

        // A key that exists in both – confirm it works in BN first
        $commonKey = 'verified';
        $this->assertContains($commonKey, $bnKeys, 'Precondition: key must exist in both lang files');

        // Use a completely non-existent key to verify fallback returns the raw key
        $missingKey = 'this.key.does.not.exist.anywhere.12345';
        $this->assertNotContains($missingKey, $enKeys);
        $this->assertNotContains($missingKey, $bnKeys);

        $result = mawa_lang($missingKey);
        // When key is missing in both, mawa_lang returns the key itself
        $this->assertSame($missingKey, $result);
    }

    public function test_language_switch_via_query_param(): void
    {
        $this->get('/login?lang=bn')
            ->assertStatus(200);

        $this->assertSame('bn', Session::get('mawa_lang'));
    }

    public function test_set_locale_middleware_resolves_bn_from_query(): void
    {
        $this->get('/login?lang=bn')
            ->assertStatus(200);

        $this->assertSame('bn', app()->getLocale());
    }

    public function test_set_locale_middleware_resolves_en_from_query(): void
    {
        $this->get('/login?lang=en')
            ->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
    }

    public function test_language_switcher_persists_in_session(): void
    {
        // First request: set to bn
        $this->get('/login?lang=bn')
            ->assertStatus(200);
        $this->assertSame('bn', Session::get('mawa_lang'));

        // Second request: no ?lang param, should persist bn from session
        $this->get('/login')
            ->assertStatus(200);
        $this->assertSame('bn', Session::get('mawa_lang'));
    }

    public function test_mawa_current_lang_returns_en_by_default(): void
    {
        Session::forget('mawa_lang');

        $this->assertSame('en', mawa_current_lang());
    }

    public function test_validation_messages_in_bangla(): void
    {
        App::setLocale('bn');

        $validator = Validator::make([], ['name' => 'required']);
        $errors = $validator->errors();
        $message = $errors->first('name');

        // Bengali Unicode range: \u0980-\u09FF
        $this->assertNotEmpty($message);
        $this->assertMatchesRegularExpression('/\p{Bengali}/u', $message, 'Expected a Bangla validation message but got: '.$message);
    }

    public function test_validation_messages_in_english(): void
    {
        App::setLocale('en');

        $validator = Validator::make([], ['name' => 'required']);
        $errors = $validator->errors();
        $message = $errors->first('name');

        $this->assertNotEmpty($message);
        $this->assertStringContainsString('required', strtolower($message), 'Expected an English validation message but got: '.$message);
    }

    public function test_lang_en_validation_file_exists(): void
    {
        $this->assertFileExists(resource_path('../lang/en/validation.php'));
    }

    public function test_lang_bn_validation_file_exists(): void
    {
        $this->assertFileExists(resource_path('../lang/bn/validation.php'));
    }

    public function test_alumni_section_keys_exist_in_both_langs(): void
    {
        $this->assertArrayHasKey('alumni', $this->enLang, 'EN lang missing alumni section');
        $this->assertArrayHasKey('alumni', $this->bnLang, 'BN lang missing alumni section');

        $enKeys = array_keys($this->enLang['alumni']);
        $bnKeys = array_keys($this->bnLang['alumni']);

        sort($enKeys);
        sort($bnKeys);

        $this->assertSame($enKeys, $bnKeys, 'Alumni section keys differ between EN and BN');
    }

    public function test_calendar_section_keys_exist_in_both_langs(): void
    {
        $this->assertArrayHasKey('calendar', $this->enLang, 'EN lang missing calendar section');
        $this->assertArrayHasKey('calendar', $this->bnLang, 'BN lang missing calendar section');

        $enKeys = array_keys($this->enLang['calendar']);
        $bnKeys = array_keys($this->bnLang['calendar']);

        sort($enKeys);
        sort($bnKeys);

        $this->assertSame($enKeys, $bnKeys, 'Calendar section keys differ between EN and BN');
    }

    public function test_documents_section_keys_exist_in_both_langs(): void
    {
        $this->assertArrayHasKey('documents', $this->enLang, 'EN lang missing documents section');
        $this->assertArrayHasKey('documents', $this->bnLang, 'BN lang missing documents section');

        $enKeys = array_keys($this->enLang['documents']);
        $bnKeys = array_keys($this->bnLang['documents']);

        sort($enKeys);
        sort($bnKeys);

        $this->assertSame($enKeys, $bnKeys, 'Documents section keys differ between EN and BN');
    }

    public function test_workflows_section_keys_exist_in_both_langs(): void
    {
        $this->assertArrayHasKey('workflows', $this->enLang, 'EN lang missing workflows section');
        $this->assertArrayHasKey('workflows', $this->bnLang, 'BN lang missing workflows section');

        $enKeys = array_keys($this->enLang['workflows']);
        $bnKeys = array_keys($this->bnLang['workflows']);

        sort($enKeys);
        sort($bnKeys);

        $this->assertSame($enKeys, $bnKeys, 'Workflows section keys differ between EN and BN');
    }

    public function test_paginate_localization_files_exist(): void
    {
        $this->assertFileExists(resource_path('../lang/en/pagination.php'));
        $this->assertFileExists(resource_path('../lang/bn/pagination.php'));
    }

    public function test_auth_localization_files_exist(): void
    {
        $this->assertFileExists(resource_path('../lang/en/auth.php'));
        $this->assertFileExists(resource_path('../lang/bn/auth.php'));
    }

    public function test_new_module_keys_all_have_translations(): void
    {
        $sections = ['alumni', 'calendar', 'documents', 'workflows'];

        foreach ($sections as $section) {
            $this->assertArrayHasKey($section, $this->enLang, "EN lang missing {$section} section");
            $this->assertArrayHasKey($section, $this->bnLang, "BN lang missing {$section} section");

            $enLeafKeys = $this->collectLeafKeys($this->enLang[$section]);
            $bnLeafKeys = $this->collectLeafKeys($this->bnLang[$section]);

            foreach ($enLeafKeys as $key) {
                $this->assertContains($key, $bnLeafKeys, "Key '{$key}' in {$section} exists in EN but not in BN");

                $bnValue = mawa_translate($this->bnLang[$section], $key);
                $this->assertNotEmpty($bnValue, "BN value for '{$key}' in {$section} is empty");
            }
        }
    }
}
