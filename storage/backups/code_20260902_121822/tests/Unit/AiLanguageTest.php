<?php

namespace Tests\Unit;

use App\Support\AiLanguage;
use PHPUnit\Framework\TestCase;

class AiLanguageTest extends TestCase
{
    public function test_detects_bangla_script(): void
    {
        $this->assertSame(AiLanguage::BN, AiLanguage::detect('কোন কোর্সে সবচেয়ে বেশি শিক্ষার্থী আছে?'));
        $this->assertStringContainsString('Bangla', AiLanguage::instruction('আজকের উপস্থিতি কত?'));
    }

    public function test_detects_banglish(): void
    {
        $this->assertSame(AiLanguage::BANGLISH, AiLanguage::detect('koyjon student ache oi course e'));
        $this->assertStringContainsString('Banglish', AiLanguage::instruction('koto student ache?'));
    }

    public function test_detects_english(): void
    {
        $this->assertSame(AiLanguage::EN, AiLanguage::detect('How many students are enrolled today?'));
        $this->assertSame('The user is writing in English. Reply in English.', AiLanguage::instruction('hello'));
    }

    public function test_detects_empty_as_english(): void
    {
        $this->assertSame(AiLanguage::EN, AiLanguage::detect(''));
    }
}
