<?php

declare(strict_types=1);

/*
 * tools/check-data-grammar.sh: the open-pr job of update-data.yml runs no PHP,
 * so it checks the data files it is about to commit against the grammar the
 * importer writes, and nothing else. A dependency that ran in the import job
 * could otherwise append code to a 700 KB literal array (security re-check on
 * #22).
 */
function grammarCheck(string $content): int
{
    $file = tempnam(sys_get_temp_dir(), 'tpp-grammar-');
    file_put_contents($file, $content);
    exec('bash ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/check-data-grammar.sh') . ' ' . escapeshellarg($file) . ' 2>/dev/null', $out, $code);
    unlink($file);

    return $code;
}

function generated(string ...$rows): string
{
    return "<?php\n// Imported by tools/import-snapshot.php from snapshot 2026-11-01 (see data/sources.json). Do not edit.\nreturn [\n" . implode('', array_map(fn ($r) => $r . "\n", $rows)) . "];\n";
}

test('the shipped data files pass', function (string $file) {
    $content = (string) file_get_contents(dirname(__DIR__, 2) . "/data/{$file}");
    // The shipped files carry the old generator's banner; the grammar is the same.
    $content = (string) preg_replace('/^\/\/ .*$/m', '// Imported by tools/import-snapshot.php from snapshot 2026-11-01 (see data/sources.json). Do not edit.', $content, 1);
    expect(grammarCheck($content))->toBe(0);
})->with(['provinces.php', 'districts.php', 'subdistricts.php']);

test('generated rows pass, including an escaped quote and an empty list', function () {
    expect(grammarCheck(generated(
        "'10'=>['name_th'=>'กรุงเทพมหานคร','name_en'=>'Bangkok','iso_code'=>'TH-10'],",
        "'100101'=>['name_th'=>'O\\'Brien \\\\ x','name_en'=>'','postal_codes'=>[]],",
        "'500101'=>['name_th'=>'ศรีภูมิ','name_en'=>'Si Phum','postal_codes'=>['50200','50300']],",
    )))->toBe(0);
});

test('anything that is not the generated grammar is refused', function (string $content) {
    expect(grammarCheck($content))->not->toBe(0);
})->with([
    'a statement appended inside the array' => [generated("'10'=>['name_th'=>'x','name_en'=>'y'],", "system('id');")],
    'code after the array' => [generated("'10'=>['name_th'=>'x','name_en'=>'y'],") . "system('id');\n"],
    'a call as a value' => [generated("'10'=>['name_th'=>system('id'),'name_en'=>'y'],")],
    'an unescaped quote breaking out of a string' => [generated("'10'=>['name_th'=>'x'.system('id').'','name_en'=>'y'],")],
    'a banner that leaves PHP mode' => [str_replace('Do not edit.', 'Do not edit. ?><?php system(1);', generated("'10'=>['name_th'=>'x','name_en'=>'y'],"))],
    'no opening tag' => [substr(generated("'10'=>['name_th'=>'x','name_en'=>'y'],"), 6)],
    'no closing bracket' => [substr(generated("'10'=>['name_th'=>'x','name_en'=>'y'],"), 0, -3)],
    'a heredoc' => [generated("'10'=>['name_th'=><<<X\nx\nX,'name_en'=>'y'],")],
]);
