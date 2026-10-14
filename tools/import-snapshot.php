<?php

/*
 * Imports a published snapshot of the Thai address dataset into data/.
 *
 *   php tools/import-snapshot.php                      the latest published snapshot
 *   php tools/import-snapshot.php --snapshot=2026-11-01
 *   php tools/import-snapshot.php --summary=pr.md      also write a change summary
 *
 * The dataset (https://data.farzai.com/thailand-address/) is built from the
 * official sources and published as immutable, versioned snapshots by the
 * thailand-address-data pipeline. This library no longer fetches the sources.
 *
 * Before anything is written:
 * - the snapshot must be listed as published (not withdrawn) in
 *   v1/snapshots.json;
 * - its manifest must match the sha256 the index records;
 * - every file must match its manifest entry;
 * - every file must be schema v1.
 *
 * data/ keeps the library's own shape: active areas only, and a Bangkok khet
 * keeps the "เขต" the library has always shipped in name_th. Not shipped in
 * the dist.
 */

declare(strict_types=1);

// Namespaced so its helpers cannot collide with a framework's global ones.
namespace Farzai\ThailandAddress\Tools\Import;

const DEFAULT_BASE = 'https://data.farzai.com';
const PUBLIC_BASE = 'https://data.farzai.com';

// Run only as a script, so the tests can load the functions.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main(array_slice($argv, 1)));
}

/** @param list<string> $args */
function main(array $args): int
{
    $options = [];
    foreach ($args as $arg) {
        if (!preg_match('/^--(snapshot|base|summary)=(.*)$/', $arg, $m)) {
            fwrite(STDERR, "unknown argument {$arg}\n");

            return 2;
        }
        $options[$m[1]] = $m[2];
    }
    try {
        $result = importSnapshot($options['base'] ?? DEFAULT_BASE, $options['snapshot'] ?? null, dirname(__DIR__));
    } catch (\Throwable $e) {
        fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");

        return 1;
    }
    if (isset($options['summary'])) {
        file_put_contents($options['summary'], $result['summary']);
    }
    fwrite(STDERR, "imported snapshot {$result['snapshot']} into data/\n");
    echo $result['snapshot'], "\n";

    return 0;
}

/**
 * @return array{snapshot: string, summary: string}
 * @throws \RuntimeException when the snapshot cannot be verified; nothing is written then
 */
function importSnapshot(string $base, ?string $snapshot, string $root): array
{
    secureUrl($base) || throw new \InvalidArgumentException('the dataset base must be https (plain http only for a local test server)');
    $snapshot === null || validSnapshotId($snapshot) || throw new \InvalidArgumentException("not a snapshot id: {$snapshot}");
    $base = rtrim($base, '/');
    $v1 = "{$base}/thailand-address/v1";

    $index = json_decode(get("{$v1}/snapshots.json"), true, flags: JSON_THROW_ON_ERROR);
    if ($snapshot === null) {
        $snapshot = (string) json_decode(get("{$v1}/latest.json"), true, flags: JSON_THROW_ON_ERROR)['snapshot'];
        validSnapshotId($snapshot) || throw new \RuntimeException("latest.json names an invalid snapshot id {$snapshot}");
    }
    $entry = null;
    foreach ($index['snapshots'] ?? [] as $candidate) {
        if (($candidate['id'] ?? null) === $snapshot) {
            $entry = $candidate;
        }
    }
    $entry !== null || throw new \RuntimeException("snapshot {$snapshot} is not in snapshots.json");
    $entry['status'] === 'published' || throw new \RuntimeException("snapshot {$snapshot} is {$entry['status']}; a withdrawn snapshot is never imported");

    $manifestJson = get("{$v1}/{$snapshot}/manifest.json");
    hash_equals((string) $entry['manifest_sha256'], hash('sha256', $manifestJson))
        || throw new \RuntimeException("{$snapshot}/manifest.json is not the manifest snapshots.json records");
    $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
    majorOne((string) ($manifest['schema_version'] ?? '')) || throw new \RuntimeException("{$snapshot} is schema {$manifest['schema_version']}; this importer reads schema v1");

    $rows = [];
    foreach (['provinces', 'districts', 'subdistricts', 'postal_codes', 'changes'] as $file) {
        $content = get("{$v1}/{$snapshot}/{$file}.json");
        hash_equals((string) ($manifest['files']["{$file}.json"]['sha256'] ?? ''), hash('sha256', $content))
            || throw new \RuntimeException("{$snapshot}/{$file}.json does not match its manifest");
        $json = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        majorOne((string) ($json['schema_version'] ?? '')) && ($json['snapshot'] ?? null) === $snapshot
            || throw new \RuntimeException("{$snapshot}/{$file}.json is not a schema v1 file of this snapshot");
        $rows[$file] = $json['data'];
    }

    $active = fn (array $list) => array_filter($list, fn (array $r) => ($r['status'] ?? null) === 'active');
    $provinces = [];
    foreach ($active($rows['provinces']) as $p) {
        $provinces[(string) $p['code']] = ['name_th' => $p['name']['th'], 'name_en' => $p['name']['en'] ?? '', 'iso_code' => $p['iso_code']];
    }
    $districts = [];
    foreach ($active($rows['districts']) as $d) {
        // The dataset stores names without their prefix; the library has always
        // shipped Bangkok's khet with it, so it is put back.
        $districts[(string) $d['code']] = ['name_th' => ($d['kind'] === 'khet' ? 'เขต' : '') . $d['name']['th'], 'name_en' => $d['name']['en'] ?? ''];
    }
    $postal = [];
    foreach ($active($rows['postal_codes']) as $link) {
        $postal[(string) $link['subdistrict_code']][] = (string) $link['postal_code'];
    }
    $subdistricts = [];
    foreach ($active($rows['subdistricts']) as $s) {
        $codes = array_values(array_unique($postal[(string) $s['code']] ?? []));
        sort($codes, SORT_STRING);
        $subdistricts[(string) $s['code']] = ['name_th' => $s['name']['th'], 'name_en' => $s['name']['en'] ?? '', 'postal_codes' => $codes];
    }
    foreach ([&$provinces, &$districts, &$subdistricts] as &$level) {
        ksort($level, SORT_STRING);
    }
    unset($level);

    // Verified in full: now write.
    $header = "// Imported by tools/import-snapshot.php from snapshot {$snapshot} (see data/sources.json). Do not edit.\n";
    writePhp("{$root}/data/provinces.php", $provinces, $header);
    writePhp("{$root}/data/districts.php", $districts, $header);
    writePhp("{$root}/data/subdistricts.php", $subdistricts, $header);
    file_put_contents("{$root}/data/sources.json", json_encode([
        'snapshot' => $snapshot,
        'schema_version' => $manifest['schema_version'],
        'manifest_sha256' => $entry['manifest_sha256'],
        'url' => PUBLIC_BASE . "/thailand-address/v1/{$snapshot}/",
        'generated_at' => $manifest['generated_at'] ?? null,
        'counts' => $manifest['counts'] ?? null,
        'sources' => $manifest['sources'] ?? [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");

    return ['snapshot' => $snapshot, 'summary' => summary($snapshot, $manifest, $rows['changes'])];
}

/**
 * @param array<string, mixed> $manifest
 * @param list<array<string, mixed>> $changes
 */
function summary(string $snapshot, array $manifest, array $changes): string
{
    $counts = ['added' => 0, 'renamed' => 0, 'abolished' => 0, 'postal_codes_changed' => 0];
    foreach ($changes as $c) {
        $counts[$c['change']] = ($counts[$c['change']] ?? 0) + 1;
    }
    $out = "Updates `data/` to snapshot [`{$snapshot}`](" . PUBLIC_BASE . "/thailand-address/v1/{$snapshot}/manifest.json) of the Thai address dataset";
    $out .= ($manifest['previous_snapshot'] ?? null) !== null ? ", against `{$manifest['previous_snapshot']}`.\n\n" : ".\n\n";
    $out .= "| change | count |\n|---|---|\n";
    foreach ($counts as $kind => $n) {
        $out .= "| {$kind} | {$n} |\n";
    }
    if ($changes !== []) {
        $out .= "\n<details><summary>First " . min(100, count($changes)) . ' of ' . count($changes) . " changes</summary>\n\n| level | code | change | before | after |\n|---|---|---|---|---|\n";
        foreach (array_slice($changes, 0, 100) as $c) {
            $out .= sprintf("| %s | %s | %s | `%s` | `%s` |\n", $c['level'], $c['code'], $c['change'], json_encode($c['before'], JSON_UNESCAPED_UNICODE), json_encode($c['after'], JSON_UNESCAPED_UNICODE));
        }
        $out .= "\n</details>\n";
    }
    $out .= "\nEvery file was checked against the snapshot's manifest, and the manifest against the sha256 in `v1/snapshots.json`. The workflow ran the test suite on the result before opening this PR. It is never merged automatically.\n";

    return $out;
}

function get(string $url): string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['User-Agent: farzai/thailand-address snapshot importer'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    ($status === 200 && is_string($body)) || throw new \RuntimeException("GET {$url} answered {$status}");

    return $body;
}

function secureUrl(string $url): bool
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return $scheme === 'https' || ($scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost'], true));
}

function validSnapshotId(string $id): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}(\.[1-9]\d*)?$/', $id) === 1;
}

function majorOne(string $version): bool
{
    return preg_match('/^1\.\d+\.\d+$/', $version) === 1;
}

/** @param array<array-key, array<string, mixed>> $rows */
function writePhp(string $path, array $rows, string $header): void
{
    $lines = ["<?php\n", $header, "return [\n"];
    foreach ($rows as $code => $row) {
        $lines[] = var_export((string) $code, true) . '=>' . literal($row) . ",\n";
    }
    $lines[] = "];\n";
    file_put_contents($path, implode('', $lines));
}

function literal(mixed $value): string
{
    if (!is_array($value)) {
        return var_export($value, true);
    }
    $parts = [];
    foreach ($value as $key => $item) {
        $parts[] = (array_is_list($value) ? '' : var_export($key, true) . '=>') . literal($item);
    }

    return '[' . implode(',', $parts) . ']';
}
