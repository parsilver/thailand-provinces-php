<?php

/**
 * Rebuilds data/*.php and data/sources.json from the official sources.
 *
 *   php tools/generate.php              use the cache, fetch what is missing
 *   php tools/generate.php --refresh    fetch every source again
 *   php tools/generate.php --offline    never touch the network
 *   php tools/generate.php --cache-dir=DIR
 *
 * Sources (see README "Data sources"):
 *   - DOPA ทำเนียบท้องที่ ccaatt.xlsx: codes, Thai and English names.
 *   - Thailand Post getSubdistrict: one request listing every subdistrict
 *     code with its postcode.
 *   - MDES post-office CSV on data.go.th (CC Attribution): postcodes by
 *     province / amphoe / tambon name.
 *
 * Politeness: an honest User-Agent, an on-disk cache so a source is fetched
 * once, at least 2 s between requests, at most 3 retries with backoff on 429 or
 * 5xx, and a hard stop on 401/403 or anything that looks like a block page.
 * Do not work around a stop; ask the publisher instead.
 *
 * Needs ext-zip, ext-simplexml and ext-mbstring. Not shipped in the dist.
 */

declare(strict_types=1);

const USER_AGENT = 'parsilver/thailand-provinces-php data generator (+https://github.com/parsilver/thailand-provinces-php)';
const MIN_GAP_SECONDS = 2.0;

const SOURCES = [
    'dopa_ccaatt' => [
        'name' => 'DOPA ทำเนียบท้องที่ (ccaatt)',
        'publisher' => 'Department of Provincial Administration (DOPA), Ministry of Interior',
        'url' => 'https://stat.bora.dopa.go.th/dload/ccaatt.xlsx',
        'landing' => 'https://stat.bora.dopa.go.th/dload/fccaa.htm',
        'method' => 'GET',
        'file' => 'ccaatt.xlsx',
        'licence' => null,
        'licence_note' => 'No licence is stated on the download page (checked 2026-10-14). Published government record data.',
        'used_for' => 'codes, Thai names, English names',
    ],
    'thailandpost_subdistricts' => [
        'name' => 'Thailand Post track web: getSubdistrict',
        'publisher' => 'Thailand Post Co., Ltd.',
        'url' => 'https://trackweb.thailandpost.co.th/post/api/web/getSubdistrict',
        'landing' => 'https://track.thailandpost.co.th/',
        'method' => 'POST',
        'file' => 'thailandpost-getSubdistrict.json',
        'licence' => null,
        'licence_note' => 'No licence is stated. Terms: https://postbase.thailandpost.co.th/th/terms-and-conditions. Thailand Post is the authority on postcodes.',
        'used_for' => 'postcodes, joined by subdistrict code',
    ],
    'mdes_postoffice' => [
        'name' => 'สถานที่ทำการไปรษณีย์ (post offices)',
        'publisher' => 'Office of the Permanent Secretary, Ministry of Digital Economy and Society (MDES)',
        'url' => 'https://datacatalog.mdes.go.th/dataset/2b4e942c-0ef2-4892-93b8-d9b5600efe0f/resource/415ea0b4-2966-40e3-a66d-ed9f92f46343/download/postal-2026.06.30.csv',
        'landing' => 'https://data.go.th/dataset/postoffice1',
        'method' => 'GET',
        'file' => 'postal-2026.06.30.csv',
        'licence' => 'Creative Commons Attributions (version not stated)',
        'licence_note' => 'Attribution: MDES, dataset "สถานที่ทำการไปรษณีย์" on data.go.th.',
        'used_for' => 'postcodes, joined by province / district / subdistrict name',
    ],
];

// Run only as a script, so the tests can load the functions.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    main(array_slice($argv, 1));
}

/** @param list<string> $args */
function main(array $args): void
{
    $root = dirname(__DIR__);
    $cacheDir = $root . '/tools/cache';
    $mode = 'cache';
    foreach ($args as $arg) {
        if ($arg === '--refresh') {
            $mode = 'refresh';
        } elseif ($arg === '--offline') {
            $mode = 'offline';
        } elseif (str_starts_with($arg, '--cache-dir=')) {
            $cacheDir = rtrim(substr($arg, 12), '/');
        } else {
            fail("unknown argument {$arg}");
        }
    }
    foreach (['zip', 'simplexml', 'mbstring'] as $ext) {
        extension_loaded($ext) || fail("ext-{$ext} is required");
    }
    is_dir($cacheDir) || mkdir($cacheDir, 0o777, true) || fail("cannot create {$cacheDir}");

    $meta = [];
    foreach (SOURCES as $id => $source) {
        $meta[$id] = obtain($id, $source, $cacheDir, $mode);
    }

    [$asOf, $areas] = readDopa("{$cacheDir}/" . SOURCES['dopa_ccaatt']['file']);
    [$postcodes, $postcodeStats] = joinPostcodes(
        $areas,
        "{$cacheDir}/" . SOURCES['thailandpost_subdistricts']['file'],
        "{$cacheDir}/" . SOURCES['mdes_postoffice']['file'],
    );
    $counts = check($areas);

    $previous = is_file("{$root}/data/sources.json") ? json_decode((string) file_get_contents("{$root}/data/sources.json"), true) : null;

    writePhp("{$root}/data/provinces.php", array_map(fn ($a) => [$a['th'], $a['en']], $areas['province']));
    writePhp("{$root}/data/districts.php", array_map(fn ($a) => [$a['th'], $a['en']], $areas['district']));
    $rows = [];
    foreach ($areas['subdistrict'] as $code => $a) {
        $rows[$code] = [$a['th'], $a['en'], $postcodes[$code] ?? []];
    }
    writePhp("{$root}/data/subdistricts.php", $rows);

    $manifest = ['sources' => []];
    foreach (SOURCES as $id => $source) {
        $manifest['sources'][$id] = [
            'name' => $source['name'],
            'publisher' => $source['publisher'],
            'url' => $source['url'],
            'landing_page' => $source['landing'],
            'method' => $source['method'],
            'fetched_at' => $meta[$id]['fetched_at'],
            'http_last_modified' => $meta[$id]['last_modified'],
            'http_etag' => $meta[$id]['etag'],
            'bytes' => $meta[$id]['bytes'],
            'sha256' => $meta[$id]['sha256'],
            'licence' => $source['licence'],
            'licence_note' => $source['licence_note'],
            'used_for' => $source['used_for'],
        ];
    }
    $manifest['sources']['dopa_ccaatt']['data_as_of'] = $asOf;
    $manifest['counts'] = $counts;
    $manifest['postcodes'] = $postcodeStats;
    file_put_contents(
        "{$root}/data/sources.json",
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    );

    out('counts: ' . json_encode($counts, JSON_UNESCAPED_UNICODE));
    out('postcodes: ' . json_encode($postcodeStats));
    if (is_array($previous) && ($previous['counts'] ?? null) !== $counts) {
        out('NOTE: counts changed from ' . json_encode($previous['counts'] ?? null) . '; update the tests deliberately.');
    }
}

/**
 * The cached copy of a source, fetching it first when the mode allows.
 *
 * @param array<string, mixed> $source
 * @return array{fetched_at: string, last_modified: ?string, etag: ?string, bytes: int, sha256: string}
 */
function obtain(string $id, array $source, string $cacheDir, string $mode): array
{
    $path = "{$cacheDir}/{$source['file']}";
    $metaPath = "{$path}.meta.json";
    if ($mode !== 'refresh' && is_file($path) && is_file($metaPath)) {
        $meta = json_decode((string) file_get_contents($metaPath), true, flags: JSON_THROW_ON_ERROR);
        hash_file('sha256', $path) === $meta['sha256'] || fail("{$path} does not match its recorded sha256; delete it or use --refresh");
        out("cache  {$id}: {$path}");

        return $meta;
    }
    $mode !== 'offline' || fail("{$id} is not cached in {$cacheDir} and --offline was given");

    $body = $source['method'] === 'POST' ? thailandPostPayload(['flag' => '0']) : null;
    [$status, $headers, $content] = politeRequest($source['method'], $source['url'], $body);
    looksRight($id, $content) || fail("{$id}: the response does not look like the data (a block or CAPTCHA page?). Stopping.");

    file_put_contents($path, $content);
    $meta = [
        'url' => $source['url'],
        'method' => $source['method'],
        'status' => $status,
        'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'last_modified' => $headers['last-modified'] ?? null,
        'etag' => $headers['etag'] ?? null,
        'content_type' => $headers['content-type'] ?? null,
        'bytes' => strlen($content),
        'sha256' => hash('sha256', $content),
    ];
    file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    out("fetch  {$id}: {$status}, {$meta['bytes']} bytes");

    return $meta;
}

/** @return array{int, array<string, string>, string} */
function politeRequest(string $method, string $url, ?string $body): array
{
    static $last = 0.0;
    for ($attempt = 0; ; $attempt++) {
        $wait = $last + MIN_GAP_SECONDS + mt_rand(0, 1000) / 1000 - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $headers = ['User-Agent: ' . USER_AGENT, 'Accept: */*'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            $headers[] = 'Origin: https://track.thailandpost.co.th';
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => 120,
            'ignore_errors' => true,
            'follow_location' => 1,
        ]]);
        $content = @file_get_contents($url, false, $context);
        $last = microtime(true);
        $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = 0;
        $parsed = [];
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
                $parsed = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsed[strtolower(trim($k))] = trim($v);
            }
        }
        out("{$method} {$url} -> {$status}");
        if ($status === 200 && is_string($content)) {
            return [$status, $parsed, $content];
        }
        if ($status === 401 || $status === 403) {
            fail("{$url} answered {$status}: blocked. Stopping; do not work around it.");
        }
        if ($status !== 429 && $status < 500) {
            fail("{$url} answered {$status}");
        }
        $attempt < 3 || fail("{$url}: giving up after 3 retries");
        sleep(2 ** ($attempt + 2));
    }
}

/**
 * The track web app's request wrapper (from its public app.js): the JSON body
 * is base64-encoded, cut into 9 chunks sent in a shuffled order, followed by a
 * base64 map of that order.
 *
 * @param array<string, string> $payload
 */
function thailandPostPayload(array $payload, int $chunks = 9): string
{
    $encoded = base64_encode(json_encode(['data' => json_encode($payload), 'd' => null], JSON_THROW_ON_ERROR));
    $size = (int) ceil(strlen($encoded) / $chunks);
    $parts = [];
    for ($i = 0; $i < $chunks; $i++) {
        $parts[] = substr($encoded, $size * $i, $size);
    }
    $order = range(0, $chunks - 1);
    shuffle($order);
    $map = implode(':', array_map(fn (int $i) => $i . strlen($parts[$i]), $order));

    return implode('', array_map(fn (int $i) => $parts[$i], $order)) . ',' . base64_encode($map);
}

function looksRight(string $id, string $content): bool
{
    return match ($id) {
        'dopa_ccaatt' => str_starts_with($content, "PK\x03\x04"),
        'mdes_postoffice' => str_contains(substr($content, 0, 400), 'Postalcode'),
        'thailandpost_subdistricts' => is_array($rows = json_decode($content, true)) && isset($rows[0]['subdistrictsCode'], $rows[0]['zipcode']),
        default => false,
    };
}

/**
 * The active rows of ccaatt.xlsx, by level and code, plus the file's as-of date.
 *
 * @return array{array{text: string, date: ?string}, array{province: array<string, array{th: string, en: string}>, district: array<string, array{th: string, en: string}>, subdistrict: array<string, array{th: string, en: string}>}}
 */
function readDopa(string $path): array
{
    $zip = new ZipArchive();
    $zip->open($path) === true || fail("cannot open {$path}");
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        foreach (simplexml_load_string($sharedXml)->children($ns)->si as $si) {
            $shared[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
        }
    }
    $sheet = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->close();

    $asOf = ['text' => '', 'date' => null];
    $areas = ['province' => [], 'district' => [], 'subdistrict' => []];
    $cancelled = 0;
    foreach ($sheet->children($ns)->sheetData->row as $row) {
        $cells = [];
        foreach ($row->children($ns)->c as $c) {
            // Attributes are unqualified; read them outside the main namespace.
            $attributes = $c->attributes();
            $col = preg_replace('/\d+/', '', (string) $attributes['r']);
            $type = (string) $attributes['t'];
            $value = $type === 's' ? $shared[(int) $c->children($ns)->v] : ($type === 'inlineStr'
                ? implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]')))
                : (string) $c->children($ns)->v);
            $cells[$col] = $value;
        }
        $code = trim($cells['A'] ?? '');
        if (!preg_match('/^\d{8}$/', $code)) {
            if (str_contains($code, 'ข้อมูล ณ')) {
                $asOf = ['text' => clean($code), 'date' => thaiDate($code)];
            }
            continue;
        }
        if (trim($cells['D'] ?? '') !== '0') {
            $cancelled++;
            continue;
        }
        [$level, $key] = match (true) {
            substr($code, 2) === '000000' => ['province', substr($code, 0, 2)],
            substr($code, 4) === '0000' => ['district', substr($code, 0, 4)],
            substr($code, 6) === '00' => ['subdistrict', substr($code, 0, 6)],
            default => fail("unexpected code {$code}"),
        };
        !isset($areas[$level][$key]) || fail("duplicate active code {$code}");
        $areas[$level][$key] = ['th' => clean($cells['B'] ?? ''), 'en' => clean($cells['C'] ?? '')];
    }
    foreach ($areas as &$level) {
        ksort($level, SORT_STRING);
    }
    unset($level);
    out("dopa: skipped {$cancelled} cancelled rows; as of " . ($asOf['date'] ?? '?'));

    return [$asOf, $areas];
}

function clean(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{200B}", '', $s)));
}

function thaiDate(string $text): ?string
{
    $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    if (!preg_match('/(\d{1,2})\s+(\S+)\s+(\d{4})/u', $text, $m) || ($month = array_search($m[2], $months, true)) === false) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', (int) $m[3] - 543, $month + 1, (int) $m[1]);
}

/**
 * Postcodes per subdistrict code: Thailand Post joined by code (when its name
 * agrees with DOPA's), MDES joined by normalised names, the union of both.
 *
 * @param array{province: array<string, array{th: string, en: string}>, district: array<string, array{th: string, en: string}>, subdistrict: array<string, array{th: string, en: string}>} $areas
 * @return array{array<string, list<string>>, array<string, int>}
 */
function joinPostcodes(array $areas, string $thailandPostPath, string $mdesPath): array
{
    $byName = [];
    foreach ($areas['subdistrict'] as $code => $a) {
        $code = (string) $code; // PHP turns numeric array keys into ints
        $key = norm($areas['province'][substr($code, 0, 2)]['th'], 'province') . '|'
            . norm($areas['district'][substr($code, 0, 4)]['th'], 'district') . '|'
            . norm($a['th'], 'subdistrict');
        $byName[$key][] = $code;
    }

    $tp = [];
    $withheld = 0;
    foreach (json_decode((string) file_get_contents($thailandPostPath), true, flags: JSON_THROW_ON_ERROR) as $row) {
        $code = (string) $row['subdistrictsCode'];
        $zip = trim((string) $row['zipcode']);
        if (!isset($areas['subdistrict'][$code]) || !preg_match('/^\d{5}$/', $zip)) {
            continue;
        }
        $a = norm((string) $row['subdistrictsNameTH'], 'subdistrict');
        $b = norm($areas['subdistrict'][$code]['th'], 'subdistrict');
        // The same code under a different name: accept spelling variants and
        // containment ("เทศบาลบางปู" / "บางปู"), withhold anything else.
        if ($a !== $b && !(similarity($a, $b) >= 0.7 || str_contains($a, $b) || str_contains($b, $a))) {
            $withheld++;
            continue;
        }
        $tp[$code][$zip] = true;
    }

    $mdes = [];
    $unmatched = 0;
    $handle = fopen($mdesPath, 'r');
    $header = fgetcsv($handle, escape: '\\');
    $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
    $col = array_flip($header);
    while (($r = fgetcsv($handle, escape: '\\')) !== false) {
        $zip = trim(str_replace("\u{200B}", '', (string) ($r[$col['Postalcode']] ?? '')));
        if (!preg_match('/^\d{5}$/', $zip)) {
            $unmatched++;
            continue;
        }
        $p = norm((string) $r[$col['Province']], 'province');
        $d = norm((string) $r[$col['Amphoe']], 'district');
        $t = norm((string) $r[$col['Tambon']], 'subdistrict');
        $codes = $byName["{$p}|{$d}|{$t}"] ?? ($d === 'เมือง' ? ($byName["{$p}|เมือง{$p}|{$t}"] ?? []) : []);
        if (count($codes) !== 1) {
            $unmatched++;
            continue;
        }
        $mdes[$codes[0]][$zip] = true;
    }
    fclose($handle);

    $postcodes = [];
    $stats = ['subdistricts' => count($areas['subdistrict']), 'with_postcode' => 0, 'both_sources' => 0, 'thailandpost_only' => 0, 'mdes_only' => 0, 'sources_disagree' => 0, 'without_postcode' => 0, 'thailandpost_withheld_name_mismatch' => $withheld, 'mdes_rows_unmatched' => $unmatched];
    foreach (array_keys($areas['subdistrict']) as $code) {
        $code = (string) $code;
        $t = array_keys($tp[$code] ?? []);
        $m = array_keys($mdes[$code] ?? []);
        if ($t === [] && $m === []) {
            $stats['without_postcode']++;
            continue;
        }
        $all = array_map('strval', array_unique([...$t, ...$m]));
        sort($all, SORT_STRING);
        $postcodes[$code] = $all;
        $stats['with_postcode']++;
        $stats[$t !== [] && $m !== [] ? 'both_sources' : ($t !== [] ? 'thailandpost_only' : 'mdes_only')]++;
        if ($t !== [] && $m !== []) {
            sort($t);
            sort($m);
            $stats['sources_disagree'] += $t != $m ? 1 : 0;
        }
    }

    return [$postcodes, $stats];
}

function norm(string $s, string $kind): string
{
    $s = preg_replace('/\s+/u', '', str_replace("\u{200B}", '', $s));
    $s = rtrim($s, '*');
    $s = match ($kind) {
        'province' => preg_replace('/^(จังหวัด|จ\.)/u', '', $s),
        'district' => preg_replace('/^(อำเภอ|เขต|อ\.)/u', '', $s),
        default => preg_replace('/^(ตำบล|แขวง|ต\.)/u', '', $s),
    };
    if ($kind === 'province' && in_array($s, ['กรุงเทพ', 'กรุงเทพฯ', 'กรุงเทพมหานคร', 'กทม', 'กทม.'], true)) {
        $s = 'กรุงเทพมหานคร';
    }

    return str_replace('ฯ', '', $s);
}

/** Ratcliff/Obershelp similarity over characters, as Python's difflib ratio(). */
function similarity(string $a, string $b): float
{
    $x = mb_str_split($a);
    $y = mb_str_split($b);
    $total = count($x) + count($y);

    return $total === 0 ? 1.0 : 2 * matchingChars($x, $y) / $total;
}

/**
 * @param list<string> $x
 * @param list<string> $y
 */
function matchingChars(array $x, array $y): int
{
    $best = $bx = $by = 0;
    for ($i = 0, $n = count($x); $i < $n; $i++) {
        for ($j = 0, $m = count($y); $j < $m; $j++) {
            $k = 0;
            while ($i + $k < $n && $j + $k < $m && $x[$i + $k] === $y[$j + $k]) {
                $k++;
            }
            if ($k > $best) {
                [$best, $bx, $by] = [$k, $i, $j];
            }
        }
    }
    if ($best === 0) {
        return 0;
    }

    return $best
        + matchingChars(array_slice($x, 0, $bx), array_slice($y, 0, $by))
        + matchingChars(array_slice($x, $bx + $best), array_slice($y, $by + $best));
}

/**
 * Sanity checks that hold for any honest refresh. The exact counts are pinned
 * by the tests, so a real change shows up there.
 *
 * @param array{province: array<string, mixed>, district: array<string, mixed>, subdistrict: array<string, mixed>} $areas
 * @return array<string, int>
 */
function check(array $areas): array
{
    count($areas['province']) === 77 || fail('expected 77 provinces, got ' . count($areas['province']));
    count($areas['district']) >= 900 || fail('fewer than 900 districts: wrong file?');
    count($areas['subdistrict']) >= 7000 || fail('fewer than 7000 subdistricts: wrong file?');
    foreach ($areas['district'] as $code => $_) {
        isset($areas['province'][substr((string) $code, 0, 2)]) || fail("district {$code} has no province");
    }
    foreach ($areas['subdistrict'] as $code => $_) {
        isset($areas['district'][substr((string) $code, 0, 4)]) || fail("subdistrict {$code} has no district");
    }
    foreach ($areas as $level) {
        foreach ($level as $code => $a) {
            ($a['th'] !== '' && $a['en'] !== '') || fail("{$code} has an empty name");
            !str_contains($a['th'] . $a['en'], '*') || fail("{$code} is marked cancelled but has a zero cancel date");
        }
    }
    $bangkokKhet = count(array_filter(array_keys($areas['district']), fn ($c) => str_starts_with((string) $c, '10')));
    $bangkokKhet === 50 || fail("expected 50 Bangkok khet, got {$bangkokKhet}");

    return [
        'provinces' => count($areas['province']),
        'districts' => count($areas['district']),
        'subdistricts' => count($areas['subdistrict']),
    ];
}

/** @param array<array-key, list<mixed>> $rows */
function writePhp(string $path, array $rows): void
{
    $lines = ["<?php\n", "// Generated by tools/generate.php from the sources in data/sources.json. Do not edit.\n", "return [\n"];
    foreach ($rows as $code => $row) {
        $lines[] = var_export((string) $code, true) . '=>' . literal($row) . ",\n";
    }
    $lines[] = "];\n";
    file_put_contents($path, implode('', $lines));
}

function literal(mixed $value): string
{
    return is_array($value) ? '[' . implode(',', array_map('literal', $value)) . ']' : var_export($value, true);
}

function out(string $line): void
{
    fwrite(STDERR, $line . "\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "error: {$message}\n");
    exit(1);
}
