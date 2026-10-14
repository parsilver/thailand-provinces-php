<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The pure helpers of tools/generate.php; the script itself is not shipped. */
final class GeneratorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/tools/generate.php';
    }

    /** @return iterable<array{string, string, float}> */
    public static function ratios(): iterable
    {
        // Reference values from Python's difflib.SequenceMatcher(None, a, b).ratio(),
        // which the research script used to accept a Thailand Post name.
        yield ['บางปู', 'เทศบาลบางปู', 0.625];
        yield ['คำนาดี', 'บึงกาฬ', 0.166667];
        yield ['หนองบัว', 'หนองบัวใต้', 0.823529];
        yield ['abc', 'abd', 0.666667];
        yield ['', '', 1.0];
    }

    #[DataProvider('ratios')]
    public function test_similarity_matches_difflib(string $a, string $b, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, \Farzai\ThailandAddress\Tools\similarity($a, $b), 0.000001);
    }

    public function test_reads_the_dopa_as_of_date(): void
    {
        $this->assertSame('2023-09-01', \Farzai\ThailandAddress\Tools\thaiDate('ข้อมูล ณ วันที่ 1 กันยายน 2566'));
        $this->assertNull(\Farzai\ThailandAddress\Tools\thaiDate('no date here'));
    }

    public function test_normalises_names_for_the_mdes_join(): void
    {
        $this->assertSame('กรุงเทพมหานคร', \Farzai\ThailandAddress\Tools\norm('กรุงเทพฯ', 'province'));
        $this->assertSame('ชลบุรี', \Farzai\ThailandAddress\Tools\norm('จังหวัด ชลบุรี', 'province'));
        $this->assertSame('พระนคร', \Farzai\ThailandAddress\Tools\norm('เขตพระนคร', 'district'));
        $this->assertSame('บางปู', \Farzai\ThailandAddress\Tools\norm('ตำบลบางปู*', 'subdistrict'));
    }

    public function test_cleans_whitespace(): void
    {
        $this->assertSame('Khet Nong Chok', \Farzai\ThailandAddress\Tools\clean("  Khet  Nong\tChok \u{200B}"));
    }

    public function test_thailand_post_payload_decodes_back_to_the_request(): void
    {
        $payload = \Farzai\ThailandAddress\Tools\thailandPostPayload(['flag' => '0']);
        [$body, $map] = explode(',', $payload);
        $chunks = [];
        $offset = 0;
        foreach (explode(':', base64_decode($map)) as $entry) {
            $index = (int) $entry[0];
            $length = (int) substr($entry, 1);
            $chunks[$index] = substr($body, $offset, $length);
            $offset += $length;
        }
        ksort($chunks);
        $decoded = json_decode(base64_decode(implode('', $chunks)), true);
        $this->assertSame(['data' => '{"flag":"0"}', 'd' => null], $decoded);
    }

    /** Review #7: an honest User-Agent and nothing that poses as Thailand Post's own web app. */
    public function test_requests_do_not_forge_the_web_app_headers(): void
    {
        $post = \Farzai\ThailandAddress\Tools\requestHeaders(true);
        $this->assertContains('User-Agent: ' . \Farzai\ThailandAddress\Tools\USER_AGENT, $post);
        $this->assertContains('Content-Type: application/json', $post);
        foreach ($post as $header) {
            $this->assertStringStartsNotWith('Origin:', $header);
            $this->assertStringStartsNotWith('X-Requested-With:', $header);
        }
        $this->assertNotContains('Content-Type: application/json', \Farzai\ThailandAddress\Tools\requestHeaders(false));
    }
}
