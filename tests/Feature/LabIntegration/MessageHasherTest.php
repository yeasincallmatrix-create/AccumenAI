<?php

namespace Tests\Feature\LabIntegration;

use App\Services\LabIntegration\MessageHasher;
use Tests\TestCase;

class MessageHasherTest extends TestCase
{
    public function test_same_content_different_line_endings_hash_identically(): void
    {
        $lf = "H|field\nP|1||MRN-1";
        $crlf = "H|field\r\nP|1||MRN-1";
        $cr = "H|field\rP|1||MRN-1";

        $this->assertSame(MessageHasher::hash($lf), MessageHasher::hash($crlf));
        $this->assertSame(MessageHasher::hash($lf), MessageHasher::hash($cr));
    }

    public function test_fixture_line_ending_variants_hash_identically(): void
    {
        $lf = file_get_contents(base_path('tests/Fixtures/LabIntegration/duplicate_of_astm_cbc_5part.txt'));
        $crlf = str_replace("\n", "\r\n", $lf);
        $cr = str_replace("\n", "\r", $lf);

        $this->assertSame(MessageHasher::hash($lf), MessageHasher::hash($crlf));
        $this->assertSame(MessageHasher::hash($lf), MessageHasher::hash($cr));
    }

    public function test_different_content_hashes_differently(): void
    {
        $this->assertNotSame(MessageHasher::hash('WBC|7.2'), MessageHasher::hash('WBC|7.3'));
    }

    public function test_hash_is_deterministic(): void
    {
        $this->assertSame(MessageHasher::hash('payload'), MessageHasher::hash('payload'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', MessageHasher::hash('payload'));
    }

    public function test_analyzer_id_affects_hash(): void
    {
        $this->assertNotSame(MessageHasher::hash('payload', 1), MessageHasher::hash('payload', 2));
        $this->assertSame(MessageHasher::hash('payload', 1), MessageHasher::hash('payload', 1));
    }
}
