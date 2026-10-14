<?php

declare(strict_types=1);

/*
 * The token scoping of update-data.yml (security review on #22): the job that
 * installs dependencies and runs the suite has a read-only token that is never
 * written to disk; the job that can push runs no Composer and no PHP, and
 * commits only the data files.
 */
function updateDataJobs(): array
{
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/update-data.yml');
    $jobs = substr($yaml, (int) strpos($yaml, "\njobs:\n") + 7);
    preg_match_all('/^  ([a-z][a-z0-9_-]*):\n(.*?)(?=^  [a-z][a-z0-9_-]*:\n|\z)/ms', $jobs, $m, PREG_SET_ORDER);

    return ['yaml' => $yaml, 'jobs' => array_column(array_map(fn (array $x) => [$x[1], $x[2]], $m), 1, 0)];
}

test('two jobs: a read-only import and a minimal pull request job', function () {
    ['yaml' => $yaml, 'jobs' => $jobs] = updateDataJobs();
    $this->assertSame(['import', 'open-pr'], array_keys($jobs));
    preg_match('/^permissions:\n((?:  .*\n)+)/m', $yaml, $m);
    $this->assertSame("  contents: read\n", $m[1], 'the workflow default is read-only');
});

test('the import job cannot write and leaves no token on disk', function () {
    $import = updateDataJobs()['jobs']['import'];
    $this->assertMatchesRegularExpression('/permissions:\n\s+contents: read\n\s+pull-requests: read\n/', $import);
    $this->assertStringNotContainsString(': write', $import);
    $this->assertStringContainsString('persist-credentials: false', $import);
    $this->assertStringContainsString('--no-scripts', $import);
    $this->assertStringContainsString('continue-on-error: true', $import, 'a failing suite still reaches the pull request');
});

test('the write job runs no Composer and no PHP, and commits only the data files', function () {
    $pr = updateDataJobs()['jobs']['open-pr'];
    $this->assertStringContainsString('contents: write', $pr);
    $this->assertStringContainsString('persist-credentials: false', $pr);
    foreach (['composer', 'setup-php', 'vendor/bin'] as $tool) {
        $this->assertStringNotContainsString($tool, $pr, $tool);
    }
    $this->assertDoesNotMatchRegularExpression('/(^|[\s;&|(])php\s/m', $pr, 'no php command');
    $this->assertStringContainsString('git add -- data/provinces.php data/districts.php data/subdistricts.php data/sources.json', $pr);
    $this->assertStringContainsString('--draft', $pr, 'a failing suite opens a draft');
    $this->assertStringContainsString('needs-tests', $pr);
    $this->assertStringContainsString('--force', $pr, 'a branch left by a closed pull request is updated, not a dead end');
});

test('the skip looks for an open pull request, not for a leftover branch', function () {
    $import = updateDataJobs()['jobs']['import'];
    $this->assertStringContainsString('gh pr list --head "data/$target" --state open', $import);
    $this->assertStringNotContainsString('git ls-remote', $import);
});
