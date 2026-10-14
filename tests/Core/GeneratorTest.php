<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Core;

// The pure helpers of tools/generate.php; the script itself is not shipped.
require_once dirname(__DIR__, 2) . '/tools/generate.php';

test('similarity matches difflib', function (string $a, string $b, float $expected) {
    $this->assertEqualsWithDelta($expected, \Farzai\ThailandAddress\Tools\similarity($a, $b), 0.000001);
})->with(function () {
    // Reference values from Python's difflib.SequenceMatcher(None, a, b).ratio(),
    // which the research script used to accept a Thailand Post name.
    yield ['บางปู', 'เทศบาลบางปู', 0.625];
    yield ['คำนาดี', 'บึงกาฬ', 0.166667];
    yield ['หนองบัว', 'หนองบัวใต้', 0.823529];
    yield ['abc', 'abd', 0.666667];
    yield ['', '', 1.0];
});

test('reads the dopa as of date', function () {
    $this->assertSame('2023-09-01', \Farzai\ThailandAddress\Tools\thaiDate('ข้อมูล ณ วันที่ 1 กันยายน 2566'));
    $this->assertNull(\Farzai\ThailandAddress\Tools\thaiDate('no date here'));
});

test('normalises names for the mdes join', function () {
    $this->assertSame('กรุงเทพมหานคร', \Farzai\ThailandAddress\Tools\norm('กรุงเทพฯ', 'province'));
    $this->assertSame('ชลบุรี', \Farzai\ThailandAddress\Tools\norm('จังหวัด ชลบุรี', 'province'));
    $this->assertSame('พระนคร', \Farzai\ThailandAddress\Tools\norm('เขตพระนคร', 'district'));
    $this->assertSame('บางปู', \Farzai\ThailandAddress\Tools\norm('ตำบลบางปู*', 'subdistrict'));
});

test('cleans whitespace', function () {
    $this->assertSame('Khet Nong Chok', \Farzai\ThailandAddress\Tools\clean("  Khet  Nong\tChok \u{200B}"));
});

test('thailand post payload decodes back to the request', function () {
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
});

/** Review #7: an honest User-Agent and nothing that poses as Thailand Post's own web app. */
test('requests do not forge the web app headers', function () {
    $post = \Farzai\ThailandAddress\Tools\requestHeaders(true);
    $this->assertContains('User-Agent: ' . \Farzai\ThailandAddress\Tools\USER_AGENT, $post);
    $this->assertContains('Content-Type: application/json', $post);
    foreach ($post as $header) {
        $this->assertStringStartsNotWith('Origin:', $header);
        $this->assertStringStartsNotWith('X-Requested-With:', $header);
    }
    $this->assertNotContains('Content-Type: application/json', \Farzai\ThailandAddress\Tools\requestHeaders(false));
});

/** A redirect may not leave https: the sources would then arrive unauthenticated. */
test('redirects stay on https', function () {
    $target = fn (string $from, string $location) => \Farzai\ThailandAddress\Tools\redirectTarget($from, $location);
    $this->assertSame('https://a.example/y', $target('https://a.example/x', 'https://a.example/y'));
    $this->assertSame('https://a.example/z?q=1', $target('https://a.example/x/y', '/z?q=1'));
    $this->assertSame('https://b.example/', $target('https://a.example/x', '//b.example/'));
    $this->assertNull($target('https://a.example/x', 'http://a.example/x'), 'https -> http was followed');
    $this->assertNull($target('https://a.example/x', 'ftp://a.example/x'));
    $this->assertNull($target('https://a.example/x', ''));
});

test('verify compares fetched bytes with the manifest', function () {
    $verify = fn (array $meta, array $manifest) => \Farzai\ThailandAddress\Tools\verifyAgainstManifest($meta, $manifest);
    $manifest = ['sources' => ['dopa' => ['sha256' => 'aa'], 'mdes' => ['sha256' => 'bb']]];
    $this->assertSame([], $verify(['dopa' => ['sha256' => 'aa'], 'mdes' => ['sha256' => 'bb']], $manifest));
    $this->assertSame(
        ['mdes: sha256 cc does not match bb in data/sources.json'],
        $verify(['dopa' => ['sha256' => 'aa'], 'mdes' => ['sha256' => 'cc']], $manifest),
    );
    $this->assertSame(['iso: not in data/sources.json'], $verify(['iso' => ['sha256' => 'dd']], $manifest));
});
