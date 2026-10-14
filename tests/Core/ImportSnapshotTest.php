<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/import-snapshot.php';

use function Farzai\ThailandAddress\Tools\Import\importSnapshot;

/*
 * The library's data comes from published snapshots of the Thai address
 * dataset (https://data.farzai.com/thailand-address/v1/). Here a small
 * snapshot is written in schema v1 and served by `php -S`.
 */

/** @param list<array<string, mixed>> $rows */
function envelopeJson(string $snapshot, array $rows): string
{
    return json_encode(['schema_version' => '1.0.0', 'snapshot' => $snapshot, 'data' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

function area(array $extra): array
{
    return $extra + ['status' => 'active', 'effective_from' => null, 'abolished_on' => null, 'replaced_by' => null];
}

/** Writes snapshot $id under $cdn and returns the files' contents. */
function writeSnapshot(string $cdn, string $id, array $index = [], array $override = []): array
{
    $files = $override + [
        'provinces.json' => envelopeJson($id, [
            area(['code' => '10', 'iso_code' => 'TH-10', 'name' => ['th' => 'กรุงเทพมหานคร', 'en' => 'Bangkok'], 'kind' => 'special_administrative_area']),
            area(['code' => '50', 'iso_code' => 'TH-50', 'name' => ['th' => 'เชียงใหม่', 'en' => 'Chiang Mai'], 'kind' => 'province']),
        ]),
        'districts.json' => envelopeJson($id, [
            area(['code' => '1001', 'province_code' => '10', 'name' => ['th' => 'พระนคร', 'en' => 'Phra Nakhon'], 'kind' => 'khet']),
            area(['code' => '1081', 'province_code' => '10', 'name' => ['th' => 'บ้านทะวาย', 'en' => 'Ban Thawai'], 'kind' => 'khet', 'status' => 'abolished', 'abolished_on' => '2006-01-01']),
            area(['code' => '5001', 'province_code' => '50', 'name' => ['th' => 'เมืองเชียงใหม่', 'en' => 'Mueang Chiang Mai'], 'kind' => 'amphoe']),
        ]),
        'subdistricts.json' => envelopeJson($id, [
            area(['code' => '100101', 'district_code' => '1001', 'province_code' => '10', 'name' => ['th' => 'พระบรมมหาราชวัง', 'en' => 'Phra Borom Maha Ratchawang'], 'kind' => 'khwaeng']),
            area(['code' => '100205', 'district_code' => '1002', 'province_code' => '10', 'name' => ['th' => 'บางซื่อ', 'en' => 'Bang Sue'], 'kind' => 'khwaeng', 'status' => 'abolished', 'abolished_on' => '2006-01-01']),
            area(['code' => '500101', 'district_code' => '5001', 'province_code' => '50', 'name' => ['th' => 'ศรีภูมิ', 'en' => 'Si Phum'], 'kind' => 'tambon']),
        ]),
        'postal_codes.json' => envelopeJson($id, [
            ['postal_code' => '10200', 'subdistrict_code' => '100101', 'sources' => ['thailandpost', 'mdes'], 'kind' => 'standard', 'coverage' => 'full', 'note' => null, 'status' => 'active', 'effective_from' => null],
            ['postal_code' => '50300', 'subdistrict_code' => '500101', 'sources' => ['mdes'], 'kind' => 'standard', 'coverage' => 'partial', 'note' => null, 'status' => 'active', 'effective_from' => null],
            ['postal_code' => '50200', 'subdistrict_code' => '500101', 'sources' => ['thailandpost', 'mdes'], 'kind' => 'standard', 'coverage' => 'partial', 'note' => null, 'status' => 'active', 'effective_from' => null],
        ]),
        'changes.json' => envelopeJson($id, [
            ['level' => 'subdistrict', 'code' => '500101', 'change' => 'postal_codes_changed', 'before' => ['postal_codes' => ['50200']], 'after' => ['postal_codes' => ['50200', '50300']], 'effective_on' => null],
        ]),
    ];
    $dir = "{$cdn}/thailand-address/v1/{$id}";
    is_dir($dir) || mkdir($dir, 0o777, true);
    $listed = [];
    foreach ($files as $name => $content) {
        file_put_contents("{$dir}/{$name}", $content);
        $listed[$name] = ['sha256' => hash('sha256', $content), 'bytes' => strlen($content), 'content_type' => 'application/json; charset=utf-8'];
    }
    $manifest = json_encode([
        'schema_version' => '1.0.0', 'snapshot' => $id, 'generated_at' => '2026-11-01T03:10:00Z', 'previous_snapshot' => null,
        'counts' => ['provinces' => ['active' => 2, 'abolished' => 0]], 'files' => $listed,
        'sources' => [['id' => 'mdes_postoffice', 'name' => 'MDES', 'publisher' => 'MDES', 'url' => 'https://example.org', 'fetched_at' => '2026-11-01T03:00:00Z', 'sha256' => str_repeat('a', 64), 'bytes' => 1, 'as_of' => '2026-06-30', 'license' => 'CC-BY', 'attribution' => 'MDES, CC-BY']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents("{$dir}/manifest.json", $manifest);
    $index[] = ['id' => $id, 'status' => 'published', 'published_at' => '2026-11-01T03:10:00Z', 'manifest_sha256' => hash('sha256', $manifest)];
    file_put_contents("{$cdn}/thailand-address/v1/snapshots.json", json_encode(['schema_version' => '1.0.0', 'snapshots' => $index]));
    file_put_contents("{$cdn}/thailand-address/v1/latest.json", json_encode(['schema_version' => '1.0.0', 'snapshot' => $id, 'manifest_sha256' => hash('sha256', $manifest)]));

    return $index;
}

beforeEach(function () {
    $this->cdn = sys_get_temp_dir() . '/tpp-cdn-' . bin2hex(random_bytes(4));
    $this->root = sys_get_temp_dir() . '/tpp-root-' . bin2hex(random_bytes(4));
    mkdir("{$this->root}/data", 0o777, true);
    mkdir($this->cdn);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $name = (string) stream_socket_get_name($socket, false);
    $this->port = (int) substr($name, strrpos($name, ':') + 1);
    fclose($socket);
    $this->server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$this->port}", '-t', $this->cdn], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 200; $i++) {
        $probe = curl_init("http://127.0.0.1:{$this->port}/");
        curl_setopt_array($probe, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 200]);
        if (curl_exec($probe) !== false) {
            break;
        }
        usleep(20_000);
    }
    $this->base = "http://127.0.0.1:{$this->port}";
});

afterEach(function () {
    proc_terminate($this->server);
    proc_close($this->server);
    exec('rm -rf ' . escapeshellarg($this->cdn) . ' ' . escapeshellarg($this->root));
});

test('imports the latest snapshot into the library data files', function () {
    writeSnapshot($this->cdn, '2026-11-01');
    $result = importSnapshot($this->base, null, $this->root);

    $this->assertSame('2026-11-01', $result['snapshot']);
    $provinces = require "{$this->root}/data/provinces.php";
    $this->assertSame(['10' => ['name_th' => 'กรุงเทพมหานคร', 'name_en' => 'Bangkok', 'iso_code' => 'TH-10'], '50' => ['name_th' => 'เชียงใหม่', 'name_en' => 'Chiang Mai', 'iso_code' => 'TH-50']], $provinces);
    $districts = require "{$this->root}/data/districts.php";
    $this->assertSame(['1001' => ['name_th' => 'เขตพระนคร', 'name_en' => 'Phra Nakhon'], '5001' => ['name_th' => 'เมืองเชียงใหม่', 'name_en' => 'Mueang Chiang Mai']], $districts, 'active only, and a khet keeps the เขต the library has always shipped');
    $subdistricts = require "{$this->root}/data/subdistricts.php";
    $this->assertSame([
        '100101' => ['name_th' => 'พระบรมมหาราชวัง', 'name_en' => 'Phra Borom Maha Ratchawang', 'postal_codes' => ['10200']],
        '500101' => ['name_th' => 'ศรีภูมิ', 'name_en' => 'Si Phum', 'postal_codes' => ['50200', '50300']],
    ], $subdistricts);

    $sources = json_decode((string) file_get_contents("{$this->root}/data/sources.json"), true);
    $this->assertSame('2026-11-01', $sources['snapshot']);
    $this->assertSame('1.0.0', $sources['schema_version']);
    $this->assertSame('https://data.farzai.com/thailand-address/v1/2026-11-01/', $sources['url']);
    $this->assertSame('CC-BY', $sources['sources'][0]['license']);
    $this->assertStringContainsString('postal_codes_changed', $result['summary']);
    $this->assertStringContainsString('500101', $result['summary']);
});

test('a pinned snapshot id is imported instead of the latest', function () {
    $index = writeSnapshot($this->cdn, '2026-10-14');
    writeSnapshot($this->cdn, '2026-11-01', $index);
    $this->assertSame('2026-10-14', importSnapshot($this->base, '2026-10-14', $this->root)['snapshot']);
});

test('nothing is written when a file does not match the manifest', function () {
    writeSnapshot($this->cdn, '2026-11-01');
    file_put_contents("{$this->cdn}/thailand-address/v1/2026-11-01/subdistricts.json", "{}\n", FILE_APPEND);
    expect(fn () => importSnapshot($this->base, null, $this->root))->toThrow(RuntimeException::class, 'subdistricts.json');
    $this->assertFileDoesNotExist("{$this->root}/data/subdistricts.php");
});

test('nothing is written when the manifest is not the one the index records', function () {
    writeSnapshot($this->cdn, '2026-11-01');
    file_put_contents("{$this->cdn}/thailand-address/v1/2026-11-01/manifest.json", "\n", FILE_APPEND);
    expect(fn () => importSnapshot($this->base, null, $this->root))->toThrow(RuntimeException::class, 'manifest');
    $this->assertFileDoesNotExist("{$this->root}/data/provinces.php");
});

test('a withdrawn snapshot is never imported', function () {
    $index = writeSnapshot($this->cdn, '2026-11-01');
    $index[0]['status'] = 'withdrawn';
    file_put_contents("{$this->cdn}/thailand-address/v1/snapshots.json", json_encode(['schema_version' => '1.0.0', 'snapshots' => $index]));
    expect(fn () => importSnapshot($this->base, '2026-11-01', $this->root))->toThrow(RuntimeException::class, 'withdrawn');
});

test('only schema v1 is understood', function () {
    writeSnapshot($this->cdn, '2026-11-01', [], ['provinces.json' => str_replace('"1.0.0"', '"2.0.0"', envelopeJson('2026-11-01', []))]);
    expect(fn () => importSnapshot($this->base, null, $this->root))->toThrow(RuntimeException::class, 'schema');
});

test('only https, or http to a local test server, and only a valid snapshot id', function () {
    expect(fn () => importSnapshot('http://data.farzai.com', null, $this->root))->toThrow(InvalidArgumentException::class);
    expect(fn () => importSnapshot($this->base, '../etc', $this->root))->toThrow(InvalidArgumentException::class);
});
