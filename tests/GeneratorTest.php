<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The pure helpers of tools/generate.php; the script itself is not shipped. */
final class GeneratorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__) . '/tools/generate.php';
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
        $this->assertEqualsWithDelta($expected, \similarity($a, $b), 0.000001);
    }

    public function test_reads_the_dopa_as_of_date(): void
    {
        $this->assertSame('2023-09-01', \thaiDate('ข้อมูล ณ วันที่ 1 กันยายน 2566'));
        $this->assertNull(\thaiDate('no date here'));
    }

    public function test_normalises_names_for_the_mdes_join(): void
    {
        $this->assertSame('กรุงเทพมหานคร', \norm('กรุงเทพฯ', 'province'));
        $this->assertSame('ชลบุรี', \norm('จังหวัด ชลบุรี', 'province'));
        $this->assertSame('พระนคร', \norm('เขตพระนคร', 'district'));
        $this->assertSame('บางปู', \norm('ตำบลบางปู*', 'subdistrict'));
    }

    public function test_cleans_whitespace(): void
    {
        $this->assertSame('Khet Nong Chok', \clean("  Khet  Nong\tChok \u{200B}"));
    }

    public function test_thailand_post_payload_decodes_back_to_the_request(): void
    {
        $payload = \thailandPostPayload(['flag' => '0']);
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
}
